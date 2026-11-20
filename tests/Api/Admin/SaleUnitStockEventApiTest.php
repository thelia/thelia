<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Api\Admin;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\SaleUnitProductSaleElements;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The stock of a sale unit edited through the admin API is told to the listeners of the stock of
 * the sale units, with the stock before and after, and carries its own low stock threshold.
 */
final class SaleUnitStockEventApiTest extends ApiTestCase
{
    /** @var list<StockLevelChange> */
    private array $changes = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = function (SaleUnitStockChangedEvent $event): void {
            $this->changes = [...$this->changes, ...$event->getChanges()];
        };
        $this->getService(EventDispatcherInterface::class)->addListener(TheliaEvents::SALE_UNIT_STOCK_CHANGED, $this->listener);
        // The transaction this test wraps itself in is not one of a caller.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->getService(EventDispatcherInterface::class)->removeListener(TheliaEvents::SALE_UNIT_STOCK_CHANGED, $this->listener);
        }

        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAnEditOfTheQuantityIsToldWithTheStockBeforeAndAfter(): void
    {
        $stock = $this->unitStock(3.0);

        foreach (['PATCH' => 'merge-patch+json', 'PUT' => 'jsonld'] as $method => $format) {
            $this->changes = [];
            $before = $this->storedQuantity($stock);
            $after = $before + 5.0;

            $response = $this->jsonRequest($method, '/api/admin/sale_unit_product_sale_elements/'.$stock->getId(), $this->body($stock, $method, ['quantity' => $after]), $this->authenticateAsAdmin(), $format);

            self::assertJsonResponseSuccessful($response);
            self::assertEquals([new StockLevelChange((int) $stock->getProductSaleElementsId(), $before, $after, (int) $stock->getSaleUnitId())], $this->changes, $method);
        }
    }

    public function testAnEditThatLeavesTheQuantityAloneTellsNothing(): void
    {
        $stock = $this->unitStock(3.0);

        $response = $this->jsonRequest('PATCH', '/api/admin/sale_unit_product_sale_elements/'.$stock->getId(), ['quantity' => 3, 'lowStockThreshold' => 2], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->changes);
    }

    public function testTheThresholdIsWrittenReadAndClearedAndNeverNegative(): void
    {
        $stock = $this->unitStock(3.0);
        $token = $this->authenticateAsAdmin();
        $uri = '/api/admin/sale_unit_product_sale_elements/'.$stock->getId();

        $written = $this->jsonRequest('PATCH', $uri, ['lowStockThreshold' => 2.5], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($written);
        self::assertEqualsWithDelta(2.5, json_decode((string) $written->getContent(), true, flags: \JSON_THROW_ON_ERROR)['lowStockThreshold'] ?? null, 0.0001);

        $refused = $this->jsonRequest('PATCH', $uri, ['lowStockThreshold' => -1], $token, 'merge-patch+json');
        self::assertSame(422, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertStringContainsString('lowStockThreshold', (string) $refused->getContent());

        $cleared = $this->jsonRequest('PATCH', $uri, ['lowStockThreshold' => null], $token, 'merge-patch+json');
        self::assertJsonResponseSuccessful($cleared);
        SaleUnitProductSaleElementsTableMap::clearInstancePool();
        self::assertNull(SaleUnitProductSaleElementsQuery::create()->findPk($stock->getId(), $this->getPropelConnection())?->getLowStockThreshold());
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function body(SaleUnitProductSaleElements $stock, string $method, array $changes): array
    {
        if ('PATCH' === $method) {
            return $changes;
        }

        return [
            'saleUnit' => '/api/admin/sale_units/'.$stock->getSaleUnitId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$stock->getProductSaleElementsId(),
            ...$changes,
        ];
    }

    private function storedQuantity(SaleUnitProductSaleElements $stock): float
    {
        SaleUnitProductSaleElementsTableMap::clearInstancePool();

        return (float) SaleUnitProductSaleElementsQuery::create()->findPk($stock->getId(), $this->getPropelConnection())?->getQuantity();
    }

    private function unitStock(float $quantity): SaleUnitProductSaleElements
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        return $factory->saleUnitProductSaleElements($factory->saleUnit(), $factory->productSaleElement($product, ['quantity' => 50]), $quantity);
    }
}
