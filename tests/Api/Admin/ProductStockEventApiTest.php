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
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The stock of the combinations carried by a product written through the admin API is told to the
 * listeners of the stock levels (before and after, the threshold crossing included) when the
 * product is replaced or patched, never when it is created.
 */
final class ProductStockEventApiTest extends ApiTestCase
{
    private const TITLE = ['en_US' => ['title' => 'A product', 'locale' => 'en_US']];

    private int $categoryId = 0;

    /** @var list<StockLevelChange> */
    private array $changes = [];

    /** @var list<LowStockReachedEvent> */
    private array $signals = [];

    /** @var array<string, \Closure> */
    private array $listeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->listeners = [
            TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED => function (StockLevelChangedEvent $event): void {
                $this->changes = [...$this->changes, ...$event->getChanges()];
            },
            TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED => function (LowStockReachedEvent $event): void {
                $this->signals[] = $event;
                // Stops the signal before the default e-mail listener.
                $event->stopPropagation();
            },
        ];

        foreach ($this->listeners as $eventName => $listener) {
            $dispatcher->addListener($eventName, $listener, 1000);
        }

        // The transaction this test wraps itself in is not one of a caller.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as $eventName => $listener) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }

        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAPatchOfTheStockOfACombinationIsToldWithTheStockBeforeAndAfter(): void
    {
        [$product, $combination] = $this->productWithCombination(3);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => self::TITLE,
            'productSaleElements' => [['id' => $combination->getId(), 'ref' => $combination->getRef(), 'quantity' => 8]],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertEquals([new StockLevelChange((int) $combination->getId(), 3.0, 8.0)], $this->changes);
    }

    public function testAPutOfTheStockOfACombinationIsToldWithTheStockBeforeAndAfter(): void
    {
        [$product, $combination] = $this->productWithCombination(3);

        $response = $this->jsonRequest('PUT', '/api/admin/products/'.$product->getId(), [
            'ref' => $product->getRef(),
            'visible' => true,
            'virtual' => false,
            'taxRule' => '/api/admin/tax_rules/'.$product->getTaxRuleId(),
            'i18ns' => self::TITLE,
            'productCategories' => [['category' => '/api/admin/categories/'.$this->categoryId, 'defaultCategory' => true]],
            'productSaleElements' => [['id' => $combination->getId(), 'ref' => $combination->getRef(), 'quantity' => 1]],
        ], $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        self::assertEquals([new StockLevelChange((int) $combination->getId(), 3.0, 1.0)], $this->changes);
    }

    public function testAPatchThatLeavesTheStockAloneTellsNothing(): void
    {
        [$product, $combination] = $this->productWithCombination(3);

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'visible' => false,
            'i18ns' => self::TITLE,
            'productSaleElements' => [['id' => $combination->getId(), 'ref' => $combination->getRef(), 'quantity' => 3]],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->changes);
    }

    public function testAPatchThatTakesTheStockUnderTheThresholdSignalsTheCrossing(): void
    {
        [$product, $combination] = $this->productWithCombination(30);
        $combination->setLowStockThreshold(10.0)->save();

        $response = $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), [
            'i18ns' => self::TITLE,
            'productSaleElements' => [['id' => $combination->getId(), 'ref' => $combination->getRef(), 'quantity' => 4]],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertCount(1, $this->signals);
        self::assertSame((int) $combination->getId(), $this->signals[0]->getProductSaleElementsId());
        self::assertSame(4.0, $this->signals[0]->getQuantity());
    }

    public function testACreationTellsNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();

        $response = $this->jsonRequest('POST', '/api/admin/products', [
            'ref' => 'API-STOCK-CREATED',
            'visible' => true,
            'virtual' => false,
            'position' => 1,
            'taxRule' => '/api/admin/tax_rules/'.$taxRule->getId(),
            'i18ns' => ['en_US' => ['title' => 'Created with a stock', 'locale' => 'en_US']],
            'productCategories' => [['category' => '/api/admin/categories/'.$category->getId(), 'defaultCategory' => true]],
            'productSaleElements' => [['ref' => 'API-STOCK-CREATED-PSE', 'quantity' => 5, 'isDefault' => true]],
        ], $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->changes);
        self::assertSame([], $this->signals);
    }

    /**
     * @return array{Product, ProductSaleElements}
     */
    private function productWithCombination(int $quantity): array
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $this->categoryId = (int) $category->getId();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $combination = $factory->productSaleElement($product, ['quantity' => $quantity]);

        return [$product, $combination];
    }
}
