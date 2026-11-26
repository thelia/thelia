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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Action\OrderReturn as OrderReturnAction;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * Stock taken from a sale unit goes back to that sale unit: a cancellation, a
 * received return and an adjustment the merchant configured on a transition all
 * follow the rule the checkout decremented by. The order's unit gets the quantity
 * back when it keeps a row for the sale element, the shared national counter
 * otherwise. Each movement is told to the listeners of the stock it moved: the
 * stock of the unit on its own event, the national counter on the event it always had.
 */
final class SaleUnitRestockTest extends ActionIntegrationTestCase
{
    use MovesOrders;
    use PlacesBundleOrders;

    private const NATIONAL_STOCK = 50.0;
    private const UNIT_STOCK = 10.0;
    private const ORDERED = 3.0;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var list<StockLevelChange> */
    private array $toldOnTheSaleUnits = [];

    /** @var list<StockLevelChange> */
    private array $toldNationally = [];

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->productSaleElements = $this->factory->productSaleElement($product, ['quantity' => self::NATIONAL_STOCK]);
        $this->saleUnit = $this->factory->saleUnit();

        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
        $this->toldOnTheSaleUnits = [];
        $this->toldNationally = [];
        $this->listen(TheliaEvents::SALE_UNIT_STOCK_CHANGED, function (SaleUnitStockChangedEvent $event): void {
            $this->toldOnTheSaleUnits = [...$this->toldOnTheSaleUnits, ...$event->getChanges()];
        });
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, function (StockLevelChangedEvent $event): void {
            $this->toldNationally = [...$this->toldNationally, ...$event->getChanges()];
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(SaleUnitActivityChecker::class)->reset();
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(TestCallerTransaction::class)->reset();
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testAnOrderPlacedOnTheSaleUnitTakesFromItsStockAndTellsIt(): void
    {
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $this->listen(TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'), $listener);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $this->getService(SaleUnitContext::class)->setSaleUnit($this->saleUnit);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->factory->cartItem($cart, $this->productSaleElements->getProduct(), $this->productSaleElements, ['quantity' => self::ORDERED, 'price' => '10.000000', 'promoPrice' => '10.000000']);

        $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame(self::UNIT_STOCK - self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK - self::ORDERED);
    }

    public function testCancellingAPaidOrderGivesTheStockBackToItsSaleUnit(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::UNIT_STOCK + self::ORDERED, $this->unitStock(), 'The cancelled quantity goes back to the unit it was sold from.');
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock(), 'The national counter did not sell it and gets nothing back.');
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK + self::ORDERED);
    }

    public function testCancellingTheOrderOfAUnitWithoutItsOwnRowGivesTheStockBackNationally(): void
    {
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::NATIONAL_STOCK + self::ORDERED, $this->nationalStock(), 'No unit row: the national counter sold it, it gets it back.');
        self::assertNull($this->unitRow(), 'No unit row is made up along the way.');
        self::assertEquals([new StockLevelChange($this->productSaleElements->getId(), self::NATIONAL_STOCK, self::NATIONAL_STOCK + self::ORDERED)], $this->toldNationally);
        self::assertSame([], $this->toldOnTheSaleUnits);
    }

    public function testPayingAnOrderWhoseStockWasNotTakenAtCreationTakesItFromItsSaleUnit(): void
    {
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(false),
        );
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(self::UNIT_STOCK - self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK - self::ORDERED);
    }

    public function testAnAdjustmentOnATransitionPutsTheStockBackInItsSaleUnit(): void
    {
        $this->adjustStockOn(OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, AdjustStockAction::OPERATION_INCREASE);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PROCESSING);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(self::UNIT_STOCK + self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK + self::ORDERED);
    }

    public function testAnAdjustmentOnATransitionTakesTheStockFromItsSaleUnit(): void
    {
        $this->adjustStockOn(OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, AdjustStockAction::OPERATION_DECREASE);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PROCESSING);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(self::UNIT_STOCK - self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK - self::ORDERED);
    }

    public function testAReceivedReturnGoesBackToTheSaleUnitOfItsOrder(): void
    {
        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PAID);

        [$return, $line] = $this->acceptedReturnOf($order);

        $this->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([
                (int) $line->getId() => ['quantity' => 2.0, 'condition' => 'as new', 'resellable' => true],
            ]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );

        self::assertSame(self::UNIT_STOCK + 2.0, $this->unitStock(), 'The two units received go back to the unit the order was placed on.');
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
        $this->assertToldOnTheSaleUnitOnly(self::UNIT_STOCK, self::UNIT_STOCK + 2.0);
    }

    public function testANationalOrderStillRestocksTheNationalCounter(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf(null, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::NATIONAL_STOCK + self::ORDERED, $this->nationalStock());
        self::assertSame(self::UNIT_STOCK, $this->unitStock(), 'A unit row is never touched by an order placed on no unit.');
        self::assertSame([], $this->toldOnTheSaleUnits);
    }

    /**
     * The movement reached the listeners of the stock of the unit, with the unit, and nothing was
     * told of the national counter, which did not move.
     */
    private function assertToldOnTheSaleUnitOnly(float $before, float $after): void
    {
        self::assertEquals(
            [new StockLevelChange($this->productSaleElements->getId(), $before, $after, $this->saleUnit->getId())],
            $this->toldOnTheSaleUnits,
        );
        self::assertSame([], $this->toldNationally);
    }

    private function orderOf(?SaleUnit $saleUnit, string $statusCode): Order
    {
        $order = $this->factory->order(null, ['statusCode' => $statusCode]);
        $order->setSaleUnitId($saleUnit?->getId())->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('REF-'.$this->productSaleElements->getId())
            ->setProductSaleElementsRef((string) $this->productSaleElements->getRef())
            ->setProductSaleElementsId($this->productSaleElements->getId())
            ->setTitle('A product sold by a sale unit')
            ->setQuantity(self::ORDERED)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->save($this->getPropelConnection());

        $order->clearOrderProducts();

        return $order;
    }

    /**
     * @return array{OrderReturn, OrderReturnLine}
     */
    private function acceptedReturnOf(Order $order): array
    {
        $orderProduct = $order->getOrderProducts()->getFirst();
        self::assertInstanceOf(OrderProduct::class, $orderProduct);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($order->getCustomer())
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED));
        $return->save($this->getPropelConnection());

        $line = (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId((int) $this->productSaleElements->getId())
            ->setQuantity(self::ORDERED)
            ->setQuantityReceived(0.0);
        $line->save($this->getPropelConnection());

        $return->clearOrderReturnLines();

        return [$return, $line];
    }

    private function adjustStockOn(string $fromCode, string $toCode, string $operation): void
    {
        (new OrderStatusAction())
            ->setTriggerType(OrderStatusActionTrigger::TRANSITION->value)
            ->setFromStatusId($this->orderStatus($fromCode)->getId())
            ->setToStatusId($this->orderStatus($toCode)->getId())
            ->setActionType(AdjustStockAction::getType())
            ->setDecodedPayload([AdjustStockAction::FIELD_OPERATION => $operation])
            ->setPosition(1)
            ->setActive(true)
            ->save();
    }

    private function nationalStock(): float
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return (float) ProductSaleElementsQuery::create()
            ->findPk($this->productSaleElements->getId(), $this->getPropelConnection())
            ?->getQuantity();
    }

    private function unitStock(): float
    {
        return (float) $this->unitRow()?->getQuantity();
    }

    private function unitRow(): ?\Thelia\Model\SaleUnitProductSaleElements
    {
        SaleUnitProductSaleElementsTableMap::clearInstancePool();

        return SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->findOne($this->getPropelConnection());
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
