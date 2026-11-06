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
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The stock of a combination edited through the admin API is told to the listeners of the stock
 * levels, with the stock before and after, as it is when the back office edits it.
 */
final class ProductSaleElementsStockEventApiTest extends ApiTestCase
{
    /** @var list<StockLevelChange> */
    private array $changes = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = function (StockLevelChangedEvent $event): void {
            $this->changes = [...$this->changes, ...$event->getChanges()];
        };
        $this->getService(EventDispatcherInterface::class)->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $this->listener);
        // The transaction this test wraps itself in is not one of a caller.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->getService(EventDispatcherInterface::class)->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $this->listener);
        }

        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAnEditOfTheQuantityIsToldWithTheStockBeforeAndAfter(): void
    {
        $combination = $this->combination(3);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['quantity' => 8], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertEquals([new StockLevelChange((int) $combination->getId(), 3.0, 8.0)], $this->changes);
    }

    public function testAnEditThatLeavesTheQuantityAloneTellsNothing(): void
    {
        $combination = $this->combination(3);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['outOfStockPolicy' => 2, 'quantity' => 3], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->changes);
    }

    public function testASaleModeSetToNullIsRefusedAsInvalid(): void
    {
        $combination = $this->combination(3);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['outOfStockPolicy' => null], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('outOfStockPolicy', (string) $response->getContent());
    }

    public function testACombinationCreatedWithoutASaleModeRefusesTheSaleAtZero(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $combination = $factory->productSaleElement($product, ['quantity' => 3]);

        $response = $this->jsonRequest('PATCH', '/api/admin/product_sale_elements/'.$combination->getId(), ['quantity' => 4], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['outOfStockPolicy'] ?? null);
    }

    private function combination(int $quantity): ProductSaleElements
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        return $factory->productSaleElement($product, ['quantity' => $quantity]);
    }
}
