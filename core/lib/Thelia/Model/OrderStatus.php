<?php

namespace Thelia\Model;

use Thelia\Model\Base\OrderStatus as BaseOrderStatus;
use Thelia\Model\Tools\PositionManagementTrait;

class OrderStatus extends BaseOrderStatus
{
    use PositionManagementTrait;

    const CODE_NOT_PAID = "not_paid";
    const CODE_PAID = "paid";
    const CODE_PROCESSING = "processing";
    const CODE_SENT = "sent";
    const CODE_CANCELED = "canceled";
    const CODE_REFUNDED = "refunded";

    /**
     * Check if the current status is NOT PAID
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_NOT_PAID.
     * if false, it will check if the order has not been paid, whatever the exact status is. The default is true.
     *
     * @return bool true if NOT PAID, false otherwise.
     */
    public function isNotPaid($exact = true)
    {
        //return $this->hasStatusHelper(OrderStatus::CODE_NOT_PAID);
        if ($exact) {
            return $this->hasStatusHelper(OrderStatus::CODE_NOT_PAID);
        }  
            return ! $this->isPaid(false);
    }

    /**
     * Check if the current status  is PAID
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_PAID.
     * if false, it will check if the order has been paid, whatever the exact status is. The default is true.
     *
     * @return bool true if PAID, false otherwise.
     */
    public function isPaid($exact = true)
    {
        return $this->hasStatusHelper(
            $exact ?
            OrderStatus::CODE_PAID :
            [ OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT ]
        );
    }

    /**
     * Check if the current status is PROCESSING
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_PROCESSING.
     * if false, it will check if the order is processing, whatever the exact status is. The default is true.
     *
     * @return bool true if PROCESSING, false otherwise.
     */
    public function isProcessing($exact = true)
    {
        return $this->hasStatusHelper(OrderStatus::CODE_PROCESSING);
    }

    /**
     * Check if the current status is SENT
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_SENT.
     * if false, it will check if the order is send, whatever the exact status is. The default is true.
     *
     * @return bool true if SENT, false otherwise.
     */
    public function isSent($exact = true)
    {
        return $this->hasStatusHelper(OrderStatus::CODE_SENT);
    }

    /**
     * Check if the current status is CANCELED
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_CANCELED.
     * if false, it will check if the order is canceled, whatever the exact status is. The default is true.
     *
     * @return bool true if CANCELED, false otherwise.
     */
    public function isCancelled($exact = true)
    {
        return $this->hasStatusHelper(OrderStatus::CODE_CANCELED);
    }

    /**
     * Check if the current status is REFUNDED
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_CANCELED.
     * if false, it will check if the order is canceled, whatever the exact status is. The default is true.
     *
     * @return bool true if REFUNDED, false otherwise.
     */
    public function isRefunded($exact = true)
    {
        return $this->hasStatusHelper(OrderStatus::CODE_REFUNDED);
    }

    /**
     * Check if the current status is $statusCode or, if $statusCode is an array, if the current
     * status is in the $statusCode array.
     *
     * @param  string|array $statusCode the status code, one of OrderStatus::CODE_xxx constants.
     * @return bool   true if the current status is in the provided status, false otherwise.
     */
    public function hasStatusHelper($statusCode)
    {
        if (\is_array($statusCode)) {
            return \in_array($this->getCode(), $statusCode);
        }  
            return $this->getCode() == $statusCode;
    }
}
