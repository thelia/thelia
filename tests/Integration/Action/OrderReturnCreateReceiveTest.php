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

namespace Thelia\Tests\Integration\Action;

use Thelia\Action\OrderReturn as OrderReturnAction;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Opening a return and pointing its reception are domain gestures, not database
 * writes a controller is free to improvise: ORDER_RETURN_CREATE and
 * ORDER_RETURN_RECEIVE are the two events every caller goes through, so the
 * eligibility gate, the numbering, the refundable amount and the restock are
 * applied once and the same way wherever the gesture comes from.
 */
final class OrderReturnCreateReceiveTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_window_days', '14');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testOpeningAReturnByDispatchPricesItAndNumbersIt(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);

        $return = $this->pendingReturn($customer, $order, $orderProduct, quantity: 2.0);

        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);

        self::assertNotNull($return->getId(), 'The return was not written.');
        self::assertMatchesRegularExpression('/^RET\d{12}$/', (string) $return->getRef());
        self::assertSame(
            OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED),
            $return->getStatusId(),
            'A return opens in the requested status, whatever the caller asked for.',
        );

        // Two units at 10.00, no tax and no discount on this order.
        self::assertSame(20.0, (float) $return->getRefundAmount());

        $line = $return->getOrderReturnLines()->getFirst();
        self::assertSame(20.0, (float) $line->getRefundAmount(), 'The single line carries the whole refund.');
        self::assertSame(
            (int) $orderProduct->getProductSaleElementsId(),
            (int) $line->getProductSaleElementsId(),
            'The line must snapshot the sale element it will put back in stock.',
        );
    }

    public function testOpeningAReturnByDispatchGoesThroughTheEligibilityGate(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 1.0);

        $return = $this->pendingReturn($customer, $order, $orderProduct, quantity: 9.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
    }

    public function testARefusedOpeningWritesNothingAtAll(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 1.0);

        $return = $this->pendingReturn($customer, $order, $orderProduct, quantity: 9.0);

        try {
            $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
        } catch (ReturnNotAllowedException) {
            // expected
        }

        self::assertSame(
            0,
            OrderReturnQuery::create()->filterByCustomerId($customer->getId())->count($this->getPropelConnection()),
            'A refused opening left a half-written return behind.',
        );
    }

    public function testPointingAPartialReceptionRestocksAndRecomputesTheRefund(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse, $line] = $this->acceptedReturn(ordered: 3.0, requested: 3.0);

        $event = (new OrderReturnEvent($return))->setReceivedLines([
            (int) $line->getId() => ['quantity' => 2.0, 'condition' => 'as new', 'resellable' => true],
        ]);

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_RECEIVE);

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(2.0, (float) $line->getQuantityReceived());
        self::assertSame('as new', $line->getReceivedCondition());

        self::assertSame(
            OrderReturnStatus::CODE_RECEIVED,
            $return->getStatusCode(),
            'Pointing the reception moves the return to received.',
        );

        // Ten in stock, two of the three requested units came back.
        self::assertSame(12.0, $this->stockOf($pse));

        // The refund follows what was received, not what was asked for.
        self::assertSame(20.0, (float) $return->getRefundAmount());
    }

    public function testANonResellableReceptionIsRecordedButNotRestocked(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse, $line] = $this->acceptedReturn(ordered: 3.0, requested: 3.0);

        $event = (new OrderReturnEvent($return))->setReceivedLines([
            (int) $line->getId() => ['quantity' => 2.0, 'resellable' => false],
        ]);

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_RECEIVE);

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(2.0, (float) $line->getQuantityReceived());
        self::assertSame(10.0, $this->stockOf($pse), 'Goods the merchant cannot sell again must not go back in stock.');
    }

    public function testReceivingMoreThanTheCustomerAskedForIsRefused(): void
    {
        [$return, $pse, $line] = $this->acceptedReturn(ordered: 3.0, requested: 2.0);

        $event = (new OrderReturnEvent($return))->setReceivedLines([
            (int) $line->getId() => ['quantity' => 20.0, 'resellable' => true],
        ]);

        try {
            $this->dispatch($event, TheliaEvents::ORDER_RETURN_RECEIVE);
            self::fail('A reception above the requested quantity was accepted.');
        } catch (ReturnNotAllowedException $exception) {
            self::assertStringContainsString('received quantity', $exception->getMessage());
        }

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(0.0, (float) $line->getQuantityReceived(), 'The refused quantity was written anyway.');
        self::assertSame(10.0, $this->stockOf($pse), 'The refused reception still moved the stock.');
        self::assertSame(OrderReturnStatus::CODE_ACCEPTED, $return->getStatusCode());
    }

    public function testAReceptionIsRefusedOnAReturnTheMerchantHasNotAcceptedYet(): void
    {
        [$return, , $line] = $this->acceptedReturn(ordered: 3.0, requested: 2.0, statusCode: OrderReturnStatus::CODE_REQUESTED);

        $event = (new OrderReturnEvent($return))->setReceivedLines([
            (int) $line->getId() => ['quantity' => 1.0, 'resellable' => true],
        ]);

        $this->expectException(ReturnNotAllowedException::class);
        $this->dispatch($event, TheliaEvents::ORDER_RETURN_RECEIVE);
    }

    /**
     * @return array{Customer, Order, OrderProductModel}
     */
    private function paidOrder(float $quantity): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        return [$customer, $order, $this->orderProduct($order, null, $quantity)];
    }

    /**
     * A return as a caller hands it over: the order, the customer and the lines
     * are theirs to set; the reference, the status and the amounts are not.
     */
    private function pendingReturn(Customer $customer, Order $order, OrderProductModel $orderProduct, float $quantity): OrderReturn
    {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setCreatedByAdmin(true);

        $return->addOrderReturnLine(
            (new OrderReturnLine())
                ->setOrderProduct($orderProduct)
                ->setQuantity($quantity),
        );

        return $return;
    }

    /**
     * @return array{OrderReturn, ProductSaleElements, OrderReturnLine}
     */
    private function acceptedReturn(float $ordered, float $requested, string $statusCode = OrderReturnStatus::CODE_ACCEPTED): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
        $pse = $this->factory->productSaleElement($product, ['quantity' => 10]);

        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProduct($order, $pse, $ordered);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode($statusCode));
        $return->save($this->getPropelConnection());

        $line = (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId((int) $pse->getId())
            ->setQuantity($requested)
            ->setQuantityReceived(0.0);
        $line->save($this->getPropelConnection());

        $return->clearOrderReturnLines();

        return [$return, $pse, $line];
    }

    private function orderProduct(Order $order, ?ProductSaleElements $pse, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef((string) ($pse?->getRef() ?? 'PSE-'.uniqid()))
            ->setProductSaleElementsId((int) ($pse?->getId() ?? 1))
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }

    private function stockOf(ProductSaleElements $pse): float
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return (float) ProductSaleElementsQuery::create()
            ->findPk($pse->getId(), $this->getPropelConnection())
            ?->getQuantity();
    }
}
