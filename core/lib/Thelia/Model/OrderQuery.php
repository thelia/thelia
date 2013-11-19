<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;
use \PDO;
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
    /**
     * PROPEL SHOULD FIX IT
     *
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   Order A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, REF, CUSTOMER_ID, INVOICE_ORDER_ADDRESS_ID, DELIVERY_ORDER_ADDRESS_ID, INVOICE_DATE, CURRENCY_ID, CURRENCY_RATE, TRANSACTION_REF, DELIVERY_REF, INVOICE_REF, POSTAGE, PAYMENT_MODULE_ID, DELIVERY_MODULE_ID, STATUS_ID, LANG_ID, CREATED_AT, UPDATED_AT FROM `order` WHERE ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new Order();
            $obj->hydrate($row);
            OrderTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

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
