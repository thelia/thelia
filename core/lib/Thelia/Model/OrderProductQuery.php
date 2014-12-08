<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\OrderProductQuery as BaseOrderProductQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'order_product' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class OrderProductQuery extends BaseOrderProductQuery
{
    public static function getSaleStats(
        $productRef,
        \DateTime $startDate = null,
        \DateTime $endDate = null,
        $orderStatus = array(2, 3, 4),
        $customerId = null
    ) {
        $query = self::create('op');

        if (null !== $customerId || null !== $startDate || null !== $endDate || count($orderStatus) > 0) {
            $subQuery = $query->useOrderQuery();

            if (null !== $customerId) {
                $subQuery->filterByCustomerId($customerId);
            }

            if (null !== $startDate) {
                $subQuery->filterByCreatedAt(
                    sprintf("%s 00:00:00", $startDate->format('Y-m-d')),
                    Criteria::GREATER_EQUAL
                );
            }

            if (null !== $startDate) {
                $subQuery->filterByCreatedAt(
                    sprintf("%s 23:59:59", $endDate->format('Y-m-d')),
                    Criteria::LESS_EQUAL
                );
            }

            if (count($orderStatus) > 0) {
                $subQuery->filterByStatusId($orderStatus, Criteria::IN);
            }

            $subQuery->endUse();
        }

        $query
            ->filterByProductRef($productRef)
            ->withColumn("SUM(`order_product`.QUANTITY)", 'TOTAL')
            ->select('TOTAL')
        ;

        $count = $query->findOne();

        return null === $count ? 0 : $count;
    }
}
// OrderProductQuery
