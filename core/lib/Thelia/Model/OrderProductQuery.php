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
use Thelia\Model\Base\OrderProductQuery as BaseOrderProductQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'order_product' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class OrderProductQuery extends BaseOrderProductQuery
{
    public static function getSaleStats(
        $productRef,
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        $orderStatusIdList = null,
        $customerId = null,
    ) {
        $query = self::create('op');

        if (null === $orderStatusIdList) {
            $orderStatusIdList = OrderStatusQuery::getPaidStatusIdList();
        }

        if (null !== $customerId || $startDate instanceof DateTime || $endDate instanceof DateTime || \count($orderStatusIdList) > 0) {
            $subQuery = $query->useOrderQuery();

            if (null !== $customerId) {
                $subQuery->filterByCustomerId($customerId);
            }

            if ($startDate instanceof DateTime) {
                $subQuery->filterByCreatedAt(
                    \sprintf('%s 00:00:00', $startDate->format('Y-m-d')),
                    Criteria::GREATER_EQUAL,
                );
            }

            if ($startDate instanceof DateTime) {
                $subQuery->filterByCreatedAt(
                    \sprintf('%s 23:59:59', $endDate->format('Y-m-d')),
                    Criteria::LESS_EQUAL,
                );
            }

            if (\count($orderStatusIdList) > 0) {
                $subQuery->filterByStatusId($orderStatusIdList, Criteria::IN);
            }

            $subQuery->endUse();
        }

        $query
            ->filterByProductRef($productRef)
            ->withColumn('SUM(`order_product`.QUANTITY)', 'TOTAL')
            ->select('TOTAL');

        $count = $query->findOne();

        return $count ?? 0;
    }
}

// OrderProductQuery
