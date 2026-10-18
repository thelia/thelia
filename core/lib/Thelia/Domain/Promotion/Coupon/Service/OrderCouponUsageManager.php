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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;

/**
 * Counts, on the coupons themselves, the usages an order consumes or gives back.
 *
 * A coupon remembered on an order (order_coupon) starts with its usage canceled:
 * it counts once the order is paid, and stops counting when the order stops
 * being paid. A line only counts when its coupon had a usage left to take, and
 * only a counted line gives one back. Both moves are idempotent, so calling them
 * twice changes nothing.
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

        $counted = 0;

        foreach ($orderCoupons as $orderCoupon) {
            if ($this->countUsageOf($orderCoupon, $order)) {
                ++$counted;
            }
        }

        return $counted;
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

        $released = 0;

        foreach ($orderCoupons as $orderCoupon) {
            if ($this->releaseUsageOf($orderCoupon, $order)) {
                ++$released;
            }
        }

        return $released;
    }

    /**
     * The line is claimed before its coupon is touched: another request counting
     * the same order finds it taken. Without a usage left, the claim is undone and
     * the line stays uncounted, so cancelling the order later gives back nothing
     * it never took.
     */
    private function countUsageOf(OrderCoupon $orderCoupon, Order $order): bool
    {
        return $this->inTransaction(function (ConnectionInterface $connection) use ($orderCoupon, $order): bool {
            if (!OrderCouponQuery::create()->switchUsageCanceled($orderCoupon->getId(), false, $connection)) {
                return false;
            }

            if (null !== ($coupon = $this->resolveCoupon($orderCoupon))
                && false === $this->couponManager->decrementQuantity($coupon, $order->getCustomerId())) {
                OrderCouponQuery::create()->switchUsageCanceled($orderCoupon->getId(), true, $connection);
                $this->syncLoadedLine($orderCoupon, true);

                return false;
            }

            $this->syncLoadedLine($orderCoupon, false);

            return true;
        });
    }

    /**
     * The line is claimed before its coupon is given the usage back: another
     * request releasing the same order finds it already released.
     */
    private function releaseUsageOf(OrderCoupon $orderCoupon, Order $order): bool
    {
        return $this->inTransaction(function (ConnectionInterface $connection) use ($orderCoupon, $order): bool {
            if (!OrderCouponQuery::create()->switchUsageCanceled($orderCoupon->getId(), true, $connection)) {
                return false;
            }

            if (null !== $coupon = $this->resolveCoupon($orderCoupon)) {
                $this->couponManager->incrementQuantity($coupon, $order->getCustomerId());
            }

            $this->syncLoadedLine($orderCoupon, true);

            return true;
        });
    }

    /**
     * The claim holds the line until the transaction ends, so a request switching
     * it at the same time waits for the outcome. A transaction opened by the
     * caller (the admin API wraps a status change in one) keeps it until its own
     * commit.
     *
     * @param callable(ConnectionInterface): bool $work
     */
    private function inTransaction(callable $work): bool
    {
        $connection = Propel::getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $done = $work($connection);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $done;
    }

    /**
     * The line was switched by a single statement, behind Propel's back: the
     * loaded object takes the stored value, without marking it as a change to save.
     */
    private function syncLoadedLine(OrderCoupon $orderCoupon, bool $usageCanceled): void
    {
        $orderCoupon->setUsageCanceled($usageCanceled);
        $orderCoupon->resetModified(OrderCouponTableMap::COL_USAGE_CANCELED);
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
