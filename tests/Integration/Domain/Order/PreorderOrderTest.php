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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A sale element that sells below zero stock, ordered through the checkout: how far it sells,
 * what the order line freezes of it, what a cancellation gives back, and what the listeners
 * of the stock levels are told, and when.
 */
final class PreorderOrderTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use MovesOrders;
    use PlacesBundleOrders;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        // The transaction this test wraps itself in is not one of a caller: the stock is told at once.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        $this->getService(TestCallerTransaction::class)->reset();
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testThreeInStockOnALimitOfTenSellsFiveAndLeavesMinusTwoOnALineMarkedAsAPreorder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        $order = $this->orderOf($product, 5.0);

        self::assertSame(-2.0, $this->stockOfProduct($product));
        [$line] = $this->linesOf($order);
        self::assertSame(1, $line->getIsPreorder());
        self::assertSame('2027-02-01', $line->getRestockDate()->format('Y-m-d'));
    }

    public function testALineTheStockCoversIsNotAPreorder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        [$line] = $this->linesOf($this->orderOf($product, 3.0));

        self::assertSame(0.0, $this->stockOfProduct($product));
        self::assertSame(0, $line->getIsPreorder());
        self::assertNull($line->getRestockDate());
    }

    public function testThePreorderOfALineIsFrozenWhateverTheCatalogueSaysLater(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0, '2027-02-01');
        $order = $this->orderOf($product, 2.0);

        ProductSaleElementsQuery::create()->filterByProductId($product->getId())->update(['RestockDate' => '2028-01-01', 'Quantity' => 50]);

        [$line] = $this->linesOf($order);
        self::assertSame(1, OrderProductQuery::create()->findPk($line->getId())->getIsPreorder());
        self::assertSame('2027-02-01', OrderProductQuery::create()->findPk($line->getId())->getRestockDate()->format('Y-m-d'));
    }

    public function testTenOnALimitOfTenFromZeroAreSoldAndTheEleventhIsRefused(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0);

        try {
            $this->orderOf($product, 11.0);
            self::fail('Eleven units on a limit of ten are refused.');
        } catch (StockShortageException) {
        }

        self::assertSame(0.0, $this->stockOfProduct($product), 'The refusal leaves the stock as it was.');

        $this->orderOf($product, 10.0);

        self::assertSame(-10.0, $this->stockOfProduct($product));

        $this->expectException(StockShortageException::class);
        $this->orderOf($product, 1.0);
    }

    public function testTheModeThatRefusesStaysAsItWas(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Refuse, 10.0);

        try {
            $this->orderOf($product, 5.0);
            self::fail('Five out of three is refused in the mode that refuses.');
        } catch (StockShortageException) {
        }

        self::assertSame(3.0, $this->stockOfProduct($product));
    }

    public function testAnUnlimitedModeSellsWhateverTheStock(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(1, OutOfStockPolicy::Unlimited, null);

        [$line] = $this->linesOf($this->orderOf($product, 100.0));

        self::assertSame(-99.0, $this->stockOfProduct($product));
        self::assertSame(1, $line->getIsPreorder());
    }

    public function testAShopThatDoesNotCheckTheStockKeepsItsHistoricalBehaviour(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::resetCache();
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0);

        [$line] = $this->linesOf($this->orderOf($product, 5.0));

        self::assertSame(0.0, $this->stockOfProduct($product), 'The stock is floored at zero as it always was.');
        self::assertSame(0, $line->getIsPreorder());
    }

    public function testCancellingAnOrderGivesThePhysicalStockBackAndReservesNothing(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0);
        $order = $this->orderOf($product, 5.0);

        self::assertSame(-2.0, $this->stockOfProduct($product));

        $order->setCancelled($this->dispatcher);

        self::assertSame(3.0, $this->stockOfProduct($product));
    }

    public function testAnOrderPaidBeforeItsStockIsTakenFreezesThePreorderWhenTheStockIsTaken(): void
    {
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0, '2027-02-01');
        $order = $this->orderOf($product, 5.0);

        self::assertSame(3.0, $this->stockOfProduct($product), 'Not taken yet: the payment module takes it at payment.');
        [$line] = $this->linesOf($order);
        self::assertSame(1, $line->getIsPreorder(), 'The confirmation e-mail goes out before the payment: the line says it is a preorder already.');
        self::assertSame('2027-02-01', $line->getRestockDate()->format('Y-m-d'));

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(-2.0, $this->stockOfProduct($product));
        $line = OrderProductQuery::create()->findPk($line->getId());
        self::assertSame(1, $line->getIsPreorder());
        self::assertSame('2027-02-01', $line->getRestockDate()->format('Y-m-d'));
    }

    public function testALineTheStockCoversIsNotAPreorderWhenTheStockIsTakenAtPayment(): void
    {
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        [$line] = $this->linesOf($this->orderOf($product, 3.0));

        self::assertSame(0, $line->getIsPreorder());
        self::assertNull($line->getRestockDate());
    }

    public function testNothingIsToldAsAPreorderAtPaymentWhenTheShopDoesNotCheckTheStock(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::resetCache();
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        [$line] = $this->linesOf($this->orderOf($product, 2.0));

        self::assertSame(0, $line->getIsPreorder());
    }

    public function testAPackWithAComponentSoldBelowZeroIsAPreorderWithTheDateOfThatComponent(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $service = $this->getService(BundleCompositionService::class);
        $first = $this->component($this->factory, '10', 5);
        $second = $this->component($this->factory, '10', 0);
        $this->setMode($second, OutOfStockPolicy::Limited, 10.0, '2027-05-01');
        $bundle = $this->bundleProduct($this->factory, $service, BundlePriceMode::Fixed, '0', \Thelia\Domain\Catalog\Bundle\BundleDiscountType::Percent, 25.0);
        $items = [
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($first), '1'),
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($second), '1'),
        ];

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $bundle, [(int) $items[0]->getId() => '1', (int) $items[1]->getId() => '1'], 2.0);

        $lines = $this->linesOf($this->placeOrder($cart, $customer, $currency, $lang));

        self::assertSame(3.0, $this->stockOfProduct($first));
        self::assertSame(-2.0, $this->stockOfProduct($second));
        [$bundleLine, $firstLine, $secondLine] = $lines;
        self::assertSame(1, $bundleLine->getIsPreorder(), 'The pack waits for its late component.');
        self::assertSame('2027-05-01', $bundleLine->getRestockDate()->format('Y-m-d'));
        self::assertSame(0, $firstLine->getIsPreorder());
        self::assertSame(1, $secondLine->getIsPreorder());
    }

    /**
     * The same pack paid later: its components are taken at the payment, both below zero, and
     * the pack waits for the later of their two dates, as it does when it is taken at placement.
     */
    public function testAPackPaidLaterWaitsForTheLatestRestockOfItsComponents(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $first = $this->component($this->factory, '10', 0);
        $second = $this->component($this->factory, '10', 0);
        $this->setMode($first, OutOfStockPolicy::Limited, 10.0, '2027-06-01');
        $this->setMode($second, OutOfStockPolicy::Limited, 10.0, '2027-05-01');
        $bundle = $this->bundleProduct($this->factory, $service, BundlePriceMode::Fixed, '0', \Thelia\Domain\Catalog\Bundle\BundleDiscountType::Percent, 25.0);
        $items = [
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($first), '1'),
            $service->addItem((int) $bundle->getId(), $this->saleElementsIdOf($second), '1'),
        ];

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $bundle, [(int) $items[0]->getId() => '1', (int) $items[1]->getId() => '1'], 1.0);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        self::assertSame(0.0, $this->stockOfProduct($first), 'Not taken yet: the payment module takes it at payment.');
        [$bundleLine, $firstLine, $secondLine] = $this->linesOf($order);
        self::assertSame([1, 1, 1], [$bundleLine->getIsPreorder(), $firstLine->getIsPreorder(), $secondLine->getIsPreorder()], 'Told as preorders before the payment.');
        self::assertSame('2027-06-01', $bundleLine->getRestockDate()->format('Y-m-d'));

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        [$bundleLine, $firstLine, $secondLine] = $this->linesOf($order);
        self::assertSame([-1.0, -1.0], [$this->stockOfProduct($first), $this->stockOfProduct($second)]);
        self::assertSame([1, 1, 1], [$bundleLine->getIsPreorder(), $firstLine->getIsPreorder(), $secondLine->getIsPreorder()]);
        self::assertSame('2027-06-01', $bundleLine->getRestockDate()->format('Y-m-d'), 'The pack waits for its latest component.');
    }

    public function testTheListenersAreToldAfterTheCommitWithTheStockBeforeAndAfter(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(22, OutOfStockPolicy::Refuse, null);
        $saleElementsId = $this->saleElementsIdOf($product);
        $received = [];
        $depthAtDispatch = null;
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, function (StockLevelChangedEvent $event) use (&$received, &$depthAtDispatch): void {
            $received[] = $event->getChanges();
            $depthAtDispatch = $this->getPropelConnection()->getNestedTransactionCount();
        });

        $depthBeforeTheOrder = $this->getPropelConnection()->getNestedTransactionCount();
        $this->orderOf($product, 3.0);

        self::assertCount(1, $received, 'One event for the order.');
        self::assertCount(1, $received[0]);
        self::assertSame($saleElementsId, $received[0][0]->productSaleElementsId);
        self::assertSame(22.0, $received[0][0]->previousQuantity);
        self::assertSame(19.0, $received[0][0]->newQuantity);
        self::assertSame($depthBeforeTheOrder, $depthAtDispatch, 'The transaction of the order is closed when the listeners hear of it.');
    }

    public function testTwoLinesOfTheSameSaleElementMakeOneChangeFromTheFirstStockToTheLast(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(10, OutOfStockPolicy::Refuse, null);
        $received = [];
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, static function (StockLevelChangedEvent $event) use (&$received): void {
            $received[] = $event->getChanges();
        });

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $product, 2.0);
        $this->addSimpleLine($cart, $product, 3.0);
        $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertCount(1, $received);
        self::assertCount(1, $received[0]);
        self::assertSame(10.0, $received[0][0]->previousQuantity);
        self::assertSame(5.0, $received[0][0]->newQuantity);
    }

    public function testNoEventIsSentWhenTheOrderIsRefused(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Refuse, null);
        $heard = 0;
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, static function () use (&$heard): void {
            ++$heard;
        });

        try {
            $this->orderOf($product, 5.0);
            self::fail('The order must be refused.');
        } catch (StockShortageException) {
        }

        self::assertSame(0, $heard);
    }

    public function testAListenerThatFailsDoesNotUndoTheOrder(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $product = $this->product(3, OutOfStockPolicy::Refuse, null);
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, static function (): void {
            throw new \RuntimeException('A listener that fails.');
        });

        $order = $this->orderOf($product, 2.0);

        self::assertNotNull($order->getId());
        self::assertSame(1.0, $this->stockOfProduct($product));
    }

    private function product(int $quantity, OutOfStockPolicy $policy, ?float $limit, ?string $restockDate = null): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity($quantity)->save();
        $this->setMode($product, $policy, $limit, $restockDate);

        return $product;
    }

    private function setMode(Product $product, OutOfStockPolicy $policy, ?float $limit, ?string $restockDate = null): void
    {
        foreach (ProductSaleElementsQuery::create()->filterByProductId($product->getId())->find() as $each) {
            $each->setOutOfStockPolicy($policy->value)->setPreorderLimit($limit)->setRestockDate($restockDate)->save();
        }
    }

    private function orderOf(Product $product, float $quantity): \Thelia\Model\Order
    {
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $product, $quantity);

        return $this->placeOrder($cart, $customer, $currency, $lang);
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function manageTheStockWhenTheOrderIsPlaced(): void
    {
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (\Thelia\Core\Event\Payment\ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );
    }
}
