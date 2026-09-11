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

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\StatusAction\Effect\AllocateInvoiceRefAction;
use Thelia\Domain\Order\StatusAction\Effect\ReleaseCouponsAction;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusActionFailureQuery;
use Thelia\Model\OrderStatusActionQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderStatusTransitionQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A shop that never touched the transition screen must behave exactly as it did
 * before the graph and the actions existed: every status reachable, the stock,
 * the invoice number and the coupons handled by the core listeners alone.
 */
final class OrderStatusDefaultConfigurationTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');

        parent::tearDown();
    }

    public function testTheSeedDeclaresNoTransitionSoEveryStatusStaysFree(): void
    {
        self::assertSame(0, OrderStatusTransitionQuery::create()->count());
    }

    public function testTheSeededActionsMirrorTheCoreListenersAndAreSwitchedOff(): void
    {
        $seeded = [];

        foreach (OrderStatusActionQuery::create()->orderById()->find() as $action) {
            self::assertFalse((bool) $action->getActive(), \sprintf('Seeded action #%d must be inactive: the core listener already does the job.', $action->getId()));
            $seeded[] = $action->getToStatus()->getCode().':'.$action->getActionType();
        }

        $expected = [
            OrderStatus::CODE_PAID.':'.AllocateInvoiceRefAction::getType(),
            OrderStatus::CODE_NOT_PAID.':'.ReleaseCouponsAction::getType(),
            OrderStatus::CODE_CANCELED.':'.ReleaseCouponsAction::getType(),
            OrderStatus::CODE_REFUNDED.':'.ReleaseCouponsAction::getType(),
        ];
        sort($expected);
        sort($seeded);

        self::assertSame($expected, $seeded);
    }

    public function testTheStandardLifecycleRunsUnchangedWithoutAnyConfiguration(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '1');

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $productSaleElements = $this->factory->productSaleElement($product, ['quantity' => 10]);
        $order = $this->factory->order();
        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('ref')
            ->setProductSaleElementsRef((string) $productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Product')
            ->setQuantity(2.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save();

        $coupon = $this->factory->coupon(['code' => 'LIFECYCLE', 'maxUsage' => 1]);
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage(), 'Paying the order still consumes the usage of its coupons, through the core listener.');
        self::assertFalse((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());

        foreach ([OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT] as $code) {
            $this->moveOrderTo($order, $code);
        }

        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertSame(OrderStatus::CODE_SENT, $reloaded->getOrderStatus()->getCode());
        self::assertNotEmpty($reloaded->getInvoiceRef(), 'The invoice numbering listener still numbers a paid order.');
        // Paying an order whose stock was not taken at creation takes the ordered
        // quantities out of stock, through the core listener, exactly as before.
        self::assertSame(8.0, (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity());
        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage(), 'Processing and shipping leave the coupon usage alone.');
        self::assertSame(0, OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->count());

        // Going back, the way the graph would forbid once configured, is still allowed.
        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);

        self::assertSame(OrderStatus::CODE_NOT_PAID, OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode());
        self::assertSame(10.0, (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity(), 'Leaving a paid status puts the quantities back, as before.');
        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage(), 'An order that is no longer paid gives its coupon usage back, as before.');
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    public function testCancellingAPaidOrderStillGivesItsCouponUsageBack(): void
    {
        $order = $this->factory->order();
        $coupon = $this->factory->coupon(['code' => 'CANCEL-ME', 'maxUsage' => 1]);
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    /**
     * A coupon as the checkout leaves it on an order: remembered, and not counted yet.
     */
    private function rememberCouponOnOrder(Order $order, Coupon $coupon): OrderCoupon
    {
        $orderCoupon = (new OrderCoupon())
            ->setOrder($order)
            ->setUsageCanceled(1)
            ->setCode($coupon->getCode())
            ->setType($coupon->getType())
            ->setAmount('5')
            ->setTitle($coupon->getTitle())
            ->setShortDescription($coupon->getShortDescription())
            ->setDescription($coupon->getDescription())
            ->setStartDate($coupon->getStartDate())
            ->setExpirationDate($coupon->getExpirationDate())
            ->setIsCumulative($coupon->getIsCumulative())
            ->setIsRemovingPostage($coupon->getIsRemovingPostage())
            ->setIsAvailableOnSpecialOffers($coupon->getIsAvailableOnSpecialOffers())
            ->setSerializedConditions($coupon->getSerializedConditions())
            ->setPerCustomerUsageCount($coupon->getPerCustomerUsageCount());
        $orderCoupon->save();

        return $orderCoupon;
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus($statusCode)->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Seeded order status '$code' is missing.");

        return $status;
    }
}
