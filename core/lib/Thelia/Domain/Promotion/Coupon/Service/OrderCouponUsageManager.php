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

namespace Thelia\Domain\Promotion\Coupon\Service;

use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;

/**
 * Counts, on the coupons themselves, the usages an order consumes or gives back.
 *
 * A coupon remembered on an order (order_coupon) starts with its usage canceled:
 * it counts once the order is paid, and stops counting when the order stops
 * being paid. Both moves are idempotent, so calling them twice changes nothing.
 */
final readonly class OrderCouponUsageManager
{
    public function __construct(
        private CouponManager $couponManager,
    ) {
    }

    /**
     * Counts the usages of the coupons of this order that are not counted yet.
     *
     * @return int how many coupon usages were counted
     */
    public function consume(Order $order): int
    {
        $orderCoupons = OrderCouponQuery::create()
            ->filterByUsageCanceled(true)
            ->findByOrderId($order->getId());

        foreach ($orderCoupons as $orderCoupon) {
            if (null !== $coupon = $this->resolveCoupon($orderCoupon)) {
                $this->couponManager->decrementQuantity($coupon, $order->getCustomerId());
            }

            $orderCoupon->setUsageCanceled(false)->save();
        }

        return \count($orderCoupons);
    }

    /**
     * Gives back the usages of the coupons of this order that are counted.
     *
     * @return int how many coupon usages were given back
     */
    public function release(Order $order): int
    {
        $orderCoupons = OrderCouponQuery::create()
            ->filterByUsageCanceled(false)
            ->findByOrderId($order->getId());

        foreach ($orderCoupons as $orderCoupon) {
            if (null !== $coupon = $this->resolveCoupon($orderCoupon)) {
                $this->couponManager->incrementQuantity($coupon, $order->getCustomerId());
            }

            $orderCoupon->setUsageCanceled(true)->save();
        }

        return \count($orderCoupons);
    }

    /**
     * The coupon row an order line came from: by id when the order kept one, by
     * code otherwise. An automatic promotion has no code, so the id is the only
     * reliable link.
     */
    private function resolveCoupon(OrderCoupon $orderCoupon): ?Coupon
    {
        if (null !== $orderCoupon->getCouponId()
            && null !== $coupon = CouponQuery::create()->findPk($orderCoupon->getCouponId())) {
            return $coupon;
        }

        $code = $orderCoupon->getCode();

        if (null !== $code && '' !== $code) {
            return CouponQuery::create()->findOneByCode($code);
        }

        return null;
    }
}
