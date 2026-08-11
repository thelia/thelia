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

use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CouponActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsCoupon(): void
    {
        $event = $this->newCreateEvent('NEW-CPN-1');

        $this->dispatch($event, TheliaEvents::COUPON_CREATE);

        $coupon = CouponQuery::create()->findOneByCode('NEW-CPN-1');
        self::assertNotNull($coupon);
        self::assertSame('10% summer promo', $coupon->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesTitleAndAmount(): void
    {
        $coupon = $this->factory->coupon(['code' => 'UPD-CPN', 'title' => 'Old Title']);

        $event = $this->newCreateEvent('UPD-CPN', ['amount' => 25.0], title: 'New Title');
        $event->setCouponModel($coupon);

        $this->dispatch($event, TheliaEvents::COUPON_UPDATE);

        $reloaded = CouponQuery::create()->findOneByCode('UPD-CPN');
        self::assertSame('New Title', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesCoupon(): void
    {
        $coupon = $this->factory->coupon(['code' => 'DEL-CPN']);
        $couponId = $coupon->getId();

        $event = new CouponDeleteEvent($coupon);
        $this->dispatch($event, TheliaEvents::COUPON_DELETE);

        self::assertNull(CouponQuery::create()->findPk($couponId));
    }

    public function testUsageIsCountedWhenTheOrderIsPaid(): void
    {
        $coupon = $this->factory->coupon(['code' => 'PAID-CPN', 'maxUsage' => 1]);
        $order = $this->factory->order();
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertFalse((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    public function testUsageIsNotCountedWhenThePaymentIsAbandoned(): void
    {
        $coupon = $this->factory->coupon(['code' => 'UNPAID-CPN', 'maxUsage' => 1]);
        $order = $this->factory->order();
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        // What BasePaymentModuleController::cancelPayment() does when the payment fails.
        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);

        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    public function testUsageIsGivenBackWhenAPaidOrderIsCanceled(): void
    {
        $coupon = $this->factory->coupon(['code' => 'CANCEL-CPN', 'maxUsage' => 1]);
        $order = $this->factory->order();
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    /**
     * Records a coupon on an order the way Action\Coupon::afterOrder() does on ORDER_BEFORE_PAYMENT:
     * the coupon is remembered, but its usage is not counted yet.
     */
    private function rememberCouponOnOrder(Order $order, Coupon $coupon): OrderCoupon
    {
        $orderCoupon = new OrderCoupon();
        $orderCoupon
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
            ->setPerCustomerUsageCount($coupon->getPerCustomerUsageCount())
            ->save();

        return $orderCoupon;
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status, "Seeded order status '$statusCode' is missing.");

        $event = new OrderEvent($order);
        $event->setStatus($status->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function newCreateEvent(
        string $code,
        array $effects = ['amount' => 10.0],
        string $title = '10% summer promo',
    ): CouponCreateOrUpdateEvent {
        return new CouponCreateOrUpdateEvent(
            code: $code,
            serviceId: 'thelia.coupon.type.remove_x_amount',
            title: $title,
            effects: $effects,
            shortDescription: '',
            description: '',
            isEnabled: true,
            expirationDate: new \DateTime('+1 month'),
            isAvailableOnSpecialOffers: false,
            isCumulative: false,
            isRemovingPostage: false,
            maxUsage: Coupon::UNLIMITED_COUPON_USE,
            locale: 'en_US',
            freeShippingForCountries: [],
            freeShippingForMethods: [],
            perCustomerUsageCount: false,
        );
    }
}
