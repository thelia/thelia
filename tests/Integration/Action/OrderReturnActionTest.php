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
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Drives a return through the state machine and checks the side effects the
 * merchant relies on: the reception restocks the resellable goods, recomputes
 * the refund, and an illegal transition is rejected.
 */
final class OrderReturnActionTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testReceivingARestocksTheResellableQuantity(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: true);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(
            OrderReturnStatus::CODE_RECEIVED,
            $return->getOrderReturnStatus()?->getCode(),
        );
        self::assertSame(12.0, $this->stockOf($pse));
        self::assertGreaterThan(0.0, (float) $return->getRefundAmount());
    }

    public function testANonResellableLineIsNotRestockedInResellableMode(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_RESELLABLE);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: false);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(10.0, $this->stockOf($pse));
    }

    public function testTheNeverModeKeepsTheStockUntouched(): void
    {
        ConfigQuery::write('order_return_restock_mode', OrderReturnAction::RESTOCK_MODE_NEVER);

        [$return, $pse] = $this->acceptedReturn(receivedQuantity: 2.0, resellable: true);

        $this->transition($return, OrderReturnStatus::CODE_RECEIVED);

        self::assertSame(10.0, $this->stockOf($pse));
    }

    public function testAnIllegalTransitionIsRejected(): void
    {
        [$return] = $this->acceptedReturn(receivedQuantity: 0.0, resellable: false);

        // accepted -> settled is not an authorized edge.
        $this->expectException(ReturnNotAllowedException::class);
        $this->transition($return, OrderReturnStatus::CODE_SETTLED);
    }

    private function transition(OrderReturn $return, string $toCode): void
    {
        $target = OrderReturnStatusQuery::create()->findOneByCode($toCode);

        $event = (new OrderReturnEvent($return))->setTargetStatusId((int) $target?->getId());

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_UPDATE_STATUS);
    }

    /**
     * @return array{OrderReturn, ProductSaleElements}
     */
    private function acceptedReturn(float $receivedQuantity, bool $resellable): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
        $pse = $this->factory->productSaleElement($product, ['quantity' => 10]);

        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProduct($order, $pse, quantity: 3.0);

        $accepted = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($accepted);
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId((int) $pse->getId())
            ->setQuantity(3.0)
            ->setQuantityReceived($receivedQuantity)
            ->setResellable($resellable)
            ->save($this->getPropelConnection());

        return [$return, $pse];
    }

    private function orderProduct(Order $order, ProductSaleElements $pse, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef((string) $pse->getRef())
            ->setProductSaleElementsId((int) $pse->getId())
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
