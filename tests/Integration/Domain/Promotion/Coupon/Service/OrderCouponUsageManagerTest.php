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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNoUsageLeftException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\OrderCouponUsageManager;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCustomerCountQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * An order counts the usages of the coupons it was placed with, found back by
 * id, or by code when the order kept no usable id.
 */
final class OrderCouponUsageManagerTest extends ActionIntegrationTestCase
{
    /**
     * An automatic promotion has no code: once the row it came from is gone, the
     * order line points at nothing, and no other promotion without a code may
     * take the usage in its place.
     */
    public function testAnOrderLineWhosePromotionIsGoneCountsAgainstNoOtherPromotion(): void
    {
        $otherPromotion = $this->automaticPromotion();
        $gonePromotion = $this->automaticPromotion();
        $order = $this->factory->order();
        $this->factory->orderCoupon($order, $gonePromotion);
        $gonePromotion->delete();

        self::assertSame(1, $this->getService(OrderCouponUsageManager::class)->consume($order));

        $otherPromotion->reload();
        self::assertSame(5, $otherPromotion->getMaxUsage());
    }

    /**
     * The last usage goes to the first order paid. The second one is paid without
     * it, and cancelling that second order gives back nothing it never took.
     */
    public function testTwoOrdersPaidOnTheLastUsageNeverRaiseTheLimit(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 1]);
        $first = $this->orderWith($coupon);
        $second = $this->orderWith($coupon);

        $this->changeStatus($first, OrderStatus::CODE_PAID);
        $this->changeStatus($second, OrderStatus::CODE_PAID);
        self::assertSame(0, $this->maxUsage($coupon));
        self::assertFalse($this->isCounted($second), 'the second order took no usage');

        $this->changeStatus($second, OrderStatus::CODE_CANCELED);
        self::assertSame(0, $this->maxUsage($coupon), 'cancelling the second order gives nothing back');

        $this->changeStatus($first, OrderStatus::CODE_CANCELED);
        self::assertSame(1, $this->maxUsage($coupon));
    }

    public function testTwoOrdersOfOneCustomerPaidOnTheirLastUsageNeverGiveItBackTwice(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 1, 'perCustomerUsageCount' => true]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $first = $this->orderWith($coupon, $customer);
        $second = $this->orderWith($coupon, $customer);

        $this->changeStatus($first, OrderStatus::CODE_PAID);
        $this->changeStatus($second, OrderStatus::CODE_PAID);
        self::assertSame(1, $this->customerCount($coupon, $customer));
        self::assertFalse($this->isCounted($second), 'the second order took no usage');

        $this->changeStatus($second, OrderStatus::CODE_CANCELED);
        self::assertSame(1, $this->customerCount($coupon, $customer), 'cancelling the second order gives nothing back');
        self::assertSame(0, $this->reloaded($coupon)->getUsagesLeft($customer->getId()));

        $this->changeStatus($first, OrderStatus::CODE_CANCELED);
        self::assertSame(0, $this->customerCount($coupon, $customer));
    }

    /**
     * Another request counts the same order while this one is counting it: the
     * line is claimed before its coupon is touched, so only one of them takes
     * the usage.
     */
    public function testAnOrderCountedByTwoRequestsAtOnceTakesOneUsage(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 5]);
        $order = $this->orderWith($coupon);
        $usageManager = $this->usageManagerInterruptedBy(
            static fn (OrderCouponUsageManager $otherRequest): int => $otherRequest->consume($order),
            onDecrement: true,
        );

        $usageManager->consume($order);

        self::assertSame(4, $this->maxUsage($coupon));
    }

    public function testAnOrderReleasedByTwoRequestsAtOnceGivesOneUsageBack(): void
    {
        $coupon = $this->factory->coupon(['maxUsage' => 4]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->factory->orderCoupon($order, $coupon, ['usageCanceled' => 0]);
        $usageManager = $this->usageManagerInterruptedBy(
            static fn (OrderCouponUsageManager $otherRequest): int => $otherRequest->release($order),
            onDecrement: false,
        );

        $usageManager->release($order);

        self::assertSame(5, $this->maxUsage($coupon));
    }

    /**
     * The usage is only counted when the order is paid: until then, an order
     * waiting for its payment holds it, and nobody may place a second order on it.
     */
    public function testACodeHeldByAnOrderWaitingForItsPaymentIsRefused(): void
    {
        $coupon = $this->typedCoupon(['maxUsage' => 1]);
        $this->orderWith($coupon);
        $this->logIn($this->factory->customer($this->factory->customerTitle()));

        $this->expectException(CouponNoUsageLeftException::class);

        $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode());
    }

    public function testACodeIsAcceptedWhileSomeUsagesAreNotHeldByUnpaidOrders(): void
    {
        $coupon = $this->typedCoupon(['maxUsage' => 2]);
        $this->orderWith($coupon);
        $this->logIn($this->factory->customer($this->factory->customerTitle()));

        self::assertSame($coupon->getCode(), $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode())->getCode());
    }

    public function testAPerCustomerCodeHeldByAnUnpaidOrderOfTheCustomerIsRefusedToThatCustomerOnly(): void
    {
        $coupon = $this->typedCoupon(['maxUsage' => 1, 'perCustomerUsageCount' => true]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->orderWith($coupon, $customer);

        $this->logIn($this->factory->customer($this->factory->customerTitle()));
        self::assertSame($coupon->getCode(), $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode())->getCode());

        $this->logIn($customer);
        $this->expectException(CouponNoUsageLeftException::class);
        $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode());
    }

    public function testCancellingTheUnpaidOrderFreesTheCode(): void
    {
        $coupon = $this->typedCoupon(['maxUsage' => 1]);
        $order = $this->orderWith($coupon);
        $this->changeStatus($order, OrderStatus::CODE_CANCELED);
        $this->logIn($this->factory->customer($this->factory->customerTitle()));

        self::assertSame($coupon->getCode(), $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode())->getCode());
        self::assertSame(1, $this->maxUsage($coupon));
    }

    /**
     * A new payment attempt on the same cart replaces its unpaid order: that order
     * does not hold the code against the cart it was placed from.
     */
    public function testTheUnpaidOrderOfTheCurrentCartDoesNotHoldTheCodeAgainstIt(): void
    {
        $coupon = $this->typedCoupon(['maxUsage' => 1]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->orderWith($coupon, $customer);
        $this->logIn($customer);
        $this->getService(RequestStack::class)->getCurrentRequest()->getSession()->set(Session::SESSION_CART_ID_NAME, $order->getCartId());

        self::assertSame($coupon->getCode(), $this->getService(CouponFactory::class)->buildCouponFromCode($coupon->getCode())->getCode());
    }

    private function automaticPromotion(): Coupon
    {
        return $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'maxUsage' => 5,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function typedCoupon(array $overrides): Coupon
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->factory->coupon(array_merge([
            'conditions' => $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions),
        ], $overrides));
    }

    private function orderWith(Coupon $coupon, ?Customer $customer = null): Order
    {
        $order = $this->factory->order($customer);
        $this->factory->orderCoupon($order, $coupon);

        return $order;
    }

    /**
     * A usage manager whose coupon manager lets another request run once, right
     * before it first takes (or gives back) a usage: the interleaving of two
     * requests on the same order, staged on one connection.
     *
     * @param \Closure(OrderCouponUsageManager): int $otherRequest
     */
    private function usageManagerInterruptedBy(\Closure $otherRequest, bool $onDecrement): OrderCouponUsageManager
    {
        $couponManager = new class($this->getService(FacadeInterface::class), $this->getService(CouponFactory::class), $this->getService(RequestStack::class)) extends CouponManager {
            public ?\Closure $meanwhile = null;

            public bool $onDecrement = true;

            public function decrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
            {
                $this->letTheOtherRequestRun(true);

                return parent::decrementQuantity($coupon, $customerId);
            }

            public function incrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
            {
                $this->letTheOtherRequestRun(false);

                return parent::incrementQuantity($coupon, $customerId);
            }

            private function letTheOtherRequestRun(bool $decrementing): void
            {
                if (null !== $this->meanwhile && $decrementing === $this->onDecrement) {
                    $meanwhile = $this->meanwhile;
                    $this->meanwhile = null;
                    $meanwhile();
                }
            }
        };

        $usageManager = new OrderCouponUsageManager($couponManager);
        $couponManager->onDecrement = $onDecrement;
        $otherUsageManager = new OrderCouponUsageManager($this->getService(CouponManager::class));
        $couponManager->meanwhile = static fn (): int => $otherRequest($otherUsageManager);

        return $usageManager;
    }

    private function changeStatus(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus(OrderStatusQuery::create()->findOneByCode($statusCode)->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function logIn(Customer $customer): void
    {
        $this->getService(SecurityContext::class)->setCustomerUser($customer);
    }

    private function reloaded(Coupon $coupon): Coupon
    {
        $coupon->reload();

        return $coupon;
    }

    private function maxUsage(Coupon $coupon): int
    {
        return $this->reloaded($coupon)->getMaxUsage();
    }

    private function customerCount(Coupon $coupon, Customer $customer): int
    {
        return (int) CouponCustomerCountQuery::create()
            ->filterByCouponId($coupon->getId())
            ->filterByCustomerId($customer->getId())
            ->select(['Count'])
            ->findOne();
    }

    private function isCounted(Order $order): bool
    {
        return !OrderCouponQuery::create()->findOneByOrderId($order->getId())->getUsageCanceled();
    }
}
