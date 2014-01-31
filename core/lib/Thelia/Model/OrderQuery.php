<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;


use Thelia\Model\Base\OrderQuery as BaseOrderQuery;

use Thelia\Model\Map\OrderTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'order' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class OrderQuery extends BaseOrderQuery
{
    public static function getMonthlySaleStats($month, $year)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = array();
        for($day=1; $day<=$numberOfDay; $day++) {
            $dayAmount = 0;
            foreach(self::create()
                        ->filterByStatusId(array(2,3,4), Criteria::IN)
                        ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                        ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL)
                        ->find() as $dayOrders) {
                $dayAmount += $dayOrders->getTotalAmount();
            }
            $stats[] = array($day-1, $dayAmount);
        }

        return $stats;
    }

    public static function getMonthlyOrdersStats($month, $year, $status = null)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = array();
        for($day=1; $day<=$numberOfDay; $day++) {
            $dayOrdersQuery = self::create()
                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL);
            if(null !== $status) {
                $dayOrdersQuery->filterByStatusId($status, Criteria::IN);
            }
            $dayOrders = $dayOrdersQuery->count();
            $stats[] = array($day-1, $dayOrders);
        }

        return $stats;
    }

    public static function getFirstOrdersStats($month, $year)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = array();
        for($day=1; $day<=$numberOfDay; $day++) {
            $dayOrdersQuery = self::create('matching_order')
                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL);

            $otherOrderJoin = new Join();
            $otherOrderJoin->addExplicitCondition(OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', 'matching_order', OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', 'other_order');
            $otherOrderJoin->setJoinType(Criteria::LEFT_JOIN);

            $dayOrdersQuery->addJoinObject($otherOrderJoin, 'other_order_join')
                ->addJoinCondition('other_order_join', '`matching_order`.`ID` <>  `other_order`.`ID`')
                ->addJoinCondition('other_order_join', '`matching_order`.`CREATED_AT` >  `other_order`.`CREATED_AT`');

            $dayOrdersQuery->where('ISNULL(`other_order`.`ID`)');

            $dayOrders = $dayOrdersQuery->count();
            $stats[] = array($day-1, $dayOrders);
        }

        return $stats;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param           $includeShipping
     *
     * @return int
     */
    public static function getSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping)
    {
        $amount = 0;
        foreach(self::create()
                    ->filterByStatusId(array(2,3,4), Criteria::IN)
                    ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
                    ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
                    ->find() as $order) {
            $tax = 0;
            $amount += $order->getTotalAmount($tax, $includeShipping);
        }

        return $amount;
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param           $status
     *
     * @return int
     */
    public static function getOrderStats(\DateTime $startDate, \DateTime $endDate, $status = array(1,2,3,4))
    {
        return self::create()
            ->filterByStatusId($status, Criteria::IN)
            ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->count();
    }

} // OrderQuery
