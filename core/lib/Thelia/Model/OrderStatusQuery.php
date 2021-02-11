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

use Thelia\Model\Base\OrderStatusQuery as BaseOrderStatusQuery;
use Thelia\Model\Exception\InvalidArgumentException;

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
    protected static $statusIdListsCache = [];
    protected static $statusModelCache = [];

    public static function getNotPaidStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_NOT_PAID);
    }

    public static function getPaidStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_PAID);
    }

    public static function getProcessingStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_PROCESSING);
    }

    public static function getSentStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_SENT);
    }

    public static function getCancelledStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_CANCELED);
    }

    public static function getRefundedStatus()
    {
        return self::getStatusModelFromCode(OrderStatus::CODE_REFUNDED);
    }

    public static function getStatusModelFromCode($statusCode)
    {
        if (! isset(self::$statusModelCache[$statusCode])) {
            self::$statusModelCache[$statusCode] = OrderStatusQuery::create()->findOneByCode($statusCode);
        }

        return self::$statusModelCache[$statusCode];
    }

    /**
     * Return the list of order status IDs for which an order is considered as not paid
     *
     * @return array
     */
    public static function getNotPaidStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_NOT_PAID);
    }

    /**
     * Return the list of order status IDs for which an order is considered as paid
     *
     * @return array
     */
    public static function getPaidStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_PAID);
    }

    /**
     * Return the list of order status IDs for which an order is considered as in process
     *
     * @return array
     */
    public static function getProcessingStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_PROCESSING);
    }

    /**
     * Return the list of order status IDs for which an order is considered as sent
     *
     * @return array
     */
    public static function getSentStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_SENT);
    }

    /**
     * Return the list of order status IDs for which an order is considered as canceled
     *
     * @return array
     */
    public static function getCanceledStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_CANCELED);
    }

    /**
     * Return the list of order status IDs for which an order is considered as refunded
     *
     * @return array
     */
    public static function getRefundedStatusIdList()
    {
        return self::getStatusIdList(OrderStatus::CODE_REFUNDED);
    }

    /**
     * Return a list of status IDs which match $statusCode value.
     *
     * @param string $statusCode the satus code
     * @return array
     */
    public static function getStatusIdList($statusCode)
    {
        if (! isset(self::$statusIdListsCache[$statusCode])) {
            $statusIdList = [];

            $statusList = OrderStatusQuery::create()->find();

            /** @var OrderStatus $status */
            foreach ($statusList as $status) {
                switch ($statusCode) {
                    case OrderStatus::CODE_NOT_PAID:
                        $match = $status->isNotPaid(false);
                        break;
                    case OrderStatus::CODE_PAID:
                        $match = $status->isPaid(false);
                        break;
                    case OrderStatus::CODE_PROCESSING:
                        $match = $status->isProcessing(false);
                        break;
                    case OrderStatus::CODE_SENT:
                        $match = $status->isSent(false);
                        break;
                    case OrderStatus::CODE_CANCELED:
                        $match = $status->isCancelled(false);
                        break;
                    case OrderStatus::CODE_REFUNDED:
                        $match = $status->isRefunded(false);
                        break;
                    default:
                        throw new InvalidArgumentException("Status code '$statusCode' is not a valid value.");
                }

                if ($match) {
                    $statusIdList[] = $status->getId();
                }
            }

            self::$statusIdListsCache[$statusCode] = $statusIdList;
        }

        return self::$statusIdListsCache[$statusCode];
    }
}
// OrderStatusQuery
