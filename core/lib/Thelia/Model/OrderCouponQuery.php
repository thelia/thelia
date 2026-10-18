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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Base\OrderCouponQuery as BaseOrderCouponQuery;
use Thelia\Model\Map\OrderCouponTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'order_coupon' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class OrderCouponQuery extends BaseOrderCouponQuery
{
    /**
     * Sets whether the usage of an order coupon line is canceled, unless the line
     * already says so. A single statement: of two requests switching the same
     * line at once, one changes it and the other waits for it, then finds it
     * already switched.
     *
     * @return bool whether this call switched the line
     */
    public function switchUsageCanceled(int $orderCouponId, bool $usageCanceled, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        $statement = $connection->prepare(
            'UPDATE order_coupon SET usage_canceled = :switched, updated_at = NOW() WHERE id = :id AND usage_canceled = :current',
        );
        $statement->execute([
            ':switched' => (int) $usageCanceled,
            ':current' => (int) !$usageCanceled,
            ':id' => $orderCouponId,
        ]);

        return 1 === $statement->rowCount();
    }

    /**
     * How many usages of a coupon the orders waiting for their payment hold.
     *
     * Such an order does not count its usage until it is paid, yet it was placed
     * with the coupon: as long as it may still be paid, the usage is promised to it.
     *
     * @param int|null $customerId   only the orders of this customer, for a coupon limited per customer
     * @param int|null $exceptCartId the cart being checked out: a new placement on it replaces its unpaid order
     */
    public function countUsagesHeldByUnpaidOrders(Coupon $coupon, ?int $customerId = null, ?int $exceptCartId = null): int
    {
        $waitingStatusIds = [];

        foreach (OrderStatusQuery::create()->find() as $status) {
            if (!$status->isPaid(false) && !$status->isCancelled() && !$status->isRefunded()) {
                $waitingStatusIds[] = $status->getId();
            }
        }

        if ([] === $waitingStatusIds) {
            return 0;
        }

        $query = self::create()
            ->filterByUsageCanceled(true)
            ->condition('by_id', OrderCouponTableMap::COL_COUPON_ID.' = ?', $coupon->getId());

        $code = $coupon->getCode();

        if (null !== $code && '' !== $code) {
            $query
                ->condition('without_id', OrderCouponTableMap::COL_COUPON_ID.' IS NULL')
                ->condition('by_code', OrderCouponTableMap::COL_CODE.' = ?', $code)
                ->combine(['without_id', 'by_code'], Criteria::LOGICAL_AND, 'by_code_only')
                ->where(['by_id', 'by_code_only'], Criteria::LOGICAL_OR);
        } else {
            $query->where(['by_id']);
        }

        $orderQuery = $query->useOrderQuery()->filterByStatusId($waitingStatusIds, Criteria::IN);

        if (null !== $customerId) {
            $orderQuery->filterByCustomerId($customerId);
        }

        if (null !== $exceptCartId) {
            $orderQuery->filterByCartId($exceptCartId, Criteria::NOT_EQUAL);
        }

        return $orderQuery->endUse()->count();
    }
}

// OrderCouponQuery
