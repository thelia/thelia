<?php

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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;
use Thelia\Model\Map\OrderTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'order' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class OrderQuery extends BaseOrderQuery
{
    /**
     * @param $month
     * @param $year
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function getMonthlySaleStats($month, $year, $includeShipping = true, $withTaxes = true)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = [];
        for ($day = 1; $day <= $numberOfDay; ++$day) {
            $dayAmount = self::getSaleStats(
                new \DateTime(sprintf('%s-%s-%s', $year, $month, $day)),
                new \DateTime(sprintf('%s-%s-%s', $year, $month, $day)),
                $includeShipping,
                $withTaxes
            );
            $stats[] = [$day - 1, $dayAmount];
        }

        return $stats;
    }

    public static function getMonthlyOrdersStats($month, $year, $status = null)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = [];
        for ($day = 1; $day <= $numberOfDay; ++$day) {
            $dayOrdersQuery = self::create()
                ->filterByInvoiceDate(sprintf('%s-%s-%s 00:00:00', $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByInvoiceDate(sprintf('%s-%s-%s 23:59:59', $year, $month, $day), Criteria::LESS_EQUAL);
            if (null !== $status) {
                $dayOrdersQuery->filterByStatusId($status, Criteria::IN);
            }
            $dayOrders = $dayOrdersQuery->count();
            $stats[] = [$day - 1, $dayOrders];
        }

        return $stats;
    }

    /**
     * @param $month
     * @param $year
     *
     * @return array
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getFirstOrdersStats($month, $year)
    {
        $numberOfDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $stats = [];
        for ($day = 1; $day <= $numberOfDay; ++$day) {
            $dayOrdersQuery = self::create()
                ->filterByCreatedAt(sprintf('%s-%s-%s 00:00:00', $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(sprintf('%s-%s-%s 23:59:59', $year, $month, $day), Criteria::LESS_EQUAL);

            $otherOrderJoin = new Join();
            $otherOrderJoin->addExplicitCondition(OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', null, OrderTableMap::TABLE_NAME, 'CUSTOMER_ID', 'other_order');
            $otherOrderJoin->setJoinType(Criteria::LEFT_JOIN);

            $dayOrdersQuery->addJoinObject($otherOrderJoin, 'other_order_join')
                ->addJoinCondition('other_order_join', '`order`.`ID` <>  `other_order`.`ID`')
                ->addJoinCondition('other_order_join', '`order`.`CREATED_AT` >  `other_order`.`CREATED_AT`');

            $dayOrdersQuery->where('ISNULL(`other_order`.`ID`)');

            $dayOrders = $dayOrdersQuery->count();
            $stats[] = [$day - 1, $dayOrders];
        }

        return $stats;
    }

    /**
     * @param bool $includeShipping
     * @param bool $withTaxes
     *
     * @return float|int
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getSaleStats(\DateTime $startDate, \DateTime $endDate, $includeShipping, $withTaxes = true)
    {
        $amount = \floatval(
            self::baseSaleStats($startDate, $endDate, 'o')
                ->innerJoinOrderProduct()
                ->withColumn('SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product`.PROMO_PRICE,`order_product`.PRICE)))', 'TOTAL')
                ->select(['TOTAL'])
                ->findOne()
        );

        if ($withTaxes) {
            $amount += \floatval(
                self::baseSaleStats($startDate, $endDate, 'o')
                    ->useOrderProductQuery()
                        ->useOrderProductTaxQuery()
                            ->withColumn('SUM((`order_product`.QUANTITY * IF(`order_product`.WAS_IN_PROMO,`order_product_tax`.PROMO_AMOUNT,`order_product_tax`.AMOUNT)))', 'TAX')
                        ->endUse()
                    ->endUse()
                    ->select(['TAX'])
                    ->findOne()
            );
        }

        $amount -= \floatval(
            self::baseSaleStats($startDate, $endDate)
                ->withColumn('SUM(`order`.discount)', 'DISCOUNT')
                ->select('DISCOUNT')
                ->findOne()
        );

        if ($includeShipping) {
            $amount += \floatval(
                self::baseSaleStats($startDate, $endDate)
                    ->withColumn('SUM(`order`.postage)', 'POSTAGE')
                    ->select('POSTAGE')
                    ->findOne()
            );
        }

        return $amount;
    }

    /**
     * @param string $modelAlias
     *
     * @return OrderQuery
     */
    protected static function baseSaleStats(\DateTime $startDate, \DateTime $endDate, $modelAlias = null)
    {
        // The sales are considered at invoice date, not order creation date
        return self::create($modelAlias)
            ->filterByInvoiceDate(sprintf('%s 00:00:00', $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByInvoiceDate(sprintf('%s 23:59:59', $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->filterByStatusId(OrderStatusQuery::getPaidStatusIdList(), Criteria::IN);
    }

    /**
     * @param int[] $status
     *
     * @return int
     */
    public static function getOrderStats(\DateTime $startDate, \DateTime $endDate, $status = null)
    {
        if ($status === null) {
            $status = OrderStatusQuery::getPaidStatusIdList();
        }

        return self::create()
            ->filterByStatusId($status, Criteria::IN)
            ->filterByCreatedAt(sprintf('%s 00:00:00', $startDate->format('Y-m-d')), Criteria::GREATER_EQUAL)
            ->filterByCreatedAt(sprintf('%s 23:59:59', $endDate->format('Y-m-d')), Criteria::LESS_EQUAL)
            ->count();
    }
}
// OrderQuery
