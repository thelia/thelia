<?php

namespace Thelia\Model;

use Thelia\Model\Base\OrderStatusQuery as BaseOrderStatusQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'order_status' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class OrderStatusQuery extends BaseOrderStatusQuery
{
    public static function getNotPaidStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID);
    }

    public static function getPaidStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);
    }

    public static function getProcessingStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PROCESSING);
    }

    public static function getSentStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT);
    }

    public static function getCancelledStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED);
    }

    public static function getRefundedStatus()
    {
        return OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_REFUNDED);
    }
}
// OrderStatusQuery
