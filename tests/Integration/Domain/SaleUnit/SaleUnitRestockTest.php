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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
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

/**
 * Stock taken from a sale unit goes back to that sale unit: a cancellation, a
 * received return and an adjustment the merchant configured on a transition all
 * follow the rule the checkout decremented by. The order's unit gets the quantity
 * back when it keeps a row for the sale element, the shared national counter
 * otherwise.
 */
final class SaleUnitRestockTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    private const NATIONAL_STOCK = 50.0;
    private const UNIT_STOCK = 10.0;
    private const ORDERED = 3.0;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->productSaleElements = $this->factory->productSaleElement($product, ['quantity' => self::NATIONAL_STOCK]);
        $this->saleUnit = $this->factory->saleUnit();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(SaleUnitActivityChecker::class)->reset();
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testCancellingAPaidOrderGivesTheStockBackToItsSaleUnit(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::UNIT_STOCK + self::ORDERED, $this->unitStock(), 'The cancelled quantity goes back to the unit it was sold from.');
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock(), 'The national counter did not sell it and gets nothing back.');
    }

    public function testCancellingTheOrderOfAUnitWithoutItsOwnRowGivesTheStockBackNationally(): void
    {
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::NATIONAL_STOCK + self::ORDERED, $this->nationalStock(), 'No unit row: the national counter sold it, it gets it back.');
        self::assertNull($this->unitRow(), 'No unit row is made up along the way.');
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
    }

    public function testAnAdjustmentOnATransitionPutsTheStockBackInItsSaleUnit(): void
    {
        $this->adjustStockOn(OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, AdjustStockAction::OPERATION_INCREASE);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PROCESSING);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(self::UNIT_STOCK + self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
    }

    public function testAnAdjustmentOnATransitionTakesTheStockFromItsSaleUnit(): void
    {
        $this->adjustStockOn(OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, AdjustStockAction::OPERATION_DECREASE);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf($this->saleUnit, OrderStatus::CODE_PROCESSING);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(self::UNIT_STOCK - self::ORDERED, $this->unitStock());
        self::assertSame(self::NATIONAL_STOCK, $this->nationalStock());
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
    }

    public function testANationalOrderStillRestocksTheNationalCounter(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
        $order = $this->orderOf(null, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(self::NATIONAL_STOCK + self::ORDERED, $this->nationalStock());
        self::assertSame(self::UNIT_STOCK, $this->unitStock(), 'A unit row is never touched by an order placed on no unit.');
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
