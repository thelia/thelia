<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
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
        for ($day=1; $day<=$numberOfDay; $day++) {
            $dayAmount = self::getSaleStats(
                new \DateTime(sprintf('%s-%s-%s', $year, $month, $day)),
                new \DateTime(sprintf('%s-%s-%s', $year, $month, $day)),
                true
            );
            $stats[] = array($day-1, $dayAmount);
        }

        return $stats;
    }

    public static function getMonthlyOrdersStats($month, $year, $status = null)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = array();
        for ($day=1; $day<=$numberOfDay; $day++) {
            $dayOrdersQuery = self::create()
                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL);
            if (null !== $status) {
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
        for ($day=1; $day<=$numberOfDay; $day++) {
            $dayOrdersQuery = self::create()
                ->filterByCreatedAt(sprintf("%s-%s-%s 00:00:00", $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(sprintf("%s-%s-%s 23:59:59", $year, $month, $day), Criteria::LESS_EQUAL);

            $otherOrderJoin = new Join();
            $otherOrderJoin->addExplicitCondition(OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', null, OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', 'other_order');
            $otherOrderJoin->setJoinType(Criteria::LEFT_JOIN);

            $dayOrdersQuery->addJoinObject($otherOrderJoin, 'other_order_join')
                ->addJoinCondition('other_order_join', '`order`.`ID` <>  `other_order`.`ID`')
                ->addJoinCondition('other_order_join', '`order`.`CREATED_AT` >  `other_order`.`CREATED_AT`');

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
        $orderTaxJoin = new Join();
        $orderTaxJoin->addExplicitCondition(OrderProductTableMap::TABLE_NAME, 'ID', null, OrderProductTaxTableMap::TABLE_NAME, 'ORDER_PRODUCT_ID', null);
        $orderTaxJoin->setJoinType(Criteria::LEFT_JOIN);

        $query = self::baseSaleStats($startDate, $endDate, 'o')
            ->innerJoinOrderProduct()
            ->addJoinObject($orderTaxJoin)
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))", 'TOTAL')
            ->withColumn("SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))", 'TAX')
            ->select(['TOTAL', 'TAX'])
        ;
        $arrayAmount = $query->findOne();

        $amount = $arrayAmount['TOTAL'] + $arrayAmount['TAX'];

        if (null === $amount) {
            $amount = 0;
        }

        $discountQuery = self::baseSaleStats($startDate, $endDate)
            ->withColumn("SUM(`order`.discount)", 'DISCOUNT')
            ->select('DISCOUNT')
        ;

        $discount = $discountQuery->findOne();

        if (null === $discount) {
            $discount = 0;
        }

        $amount = $amount - $discount;

        if ($includeShipping) {
            $query = self::baseSaleStats($startDate, $endDate)
                ->withColumn("SUM(`order`.postage)", 'POSTAGE')
                ->select('POSTAGE')
            ;

            $amount += $query->findOne();
        }

        return null === $amount ? 0 : round($amount, 2);
    }

    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null)
    {
        return self::create($modelAlias)
            ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId([2, 3, 4], Criteria::IN);
    }


    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param           $status
     *
     * @return int
     */
    public static function getOrderStats(\DateTime $startDate, \DateTime $endDate, $status = array(1, 2, 3, 4))
    {
        return self::create()
            ->filterByStatusId($status, Criteria::IN)
            ->filterByCreatedAt(sprintf("%s 00:00:00", $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt(sprintf("%s 23:59:59", $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->count();
    }
}
// OrderQuery
