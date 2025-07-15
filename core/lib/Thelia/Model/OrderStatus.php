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

use Thelia\Model\Base\OrderStatus as BaseOrderStatus;
use Thelia\Model\Tools\PositionManagementTrait;

class OrderStatus extends BaseOrderStatus
{
    use PositionManagementTrait;

    public const CODE_NOT_PAID = 'not_paid';
    public const CODE_PAID = 'paid';
    public const CODE_PROCESSING = 'processing';
    public const CODE_SENT = 'sent';
    public const CODE_CANCELED = 'canceled';
    public const CODE_REFUNDED = 'refunded';

    /**
     * Check if the current status is NOT PAID.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_NOT_PAID.
     *                    if false, it will check if the order has not been paid, whatever the exact status is. The default is true.
     *
     * @return bool true if NOT PAID, false otherwise
     */
    public function isNotPaid(bool $exact = true): bool
    {
        // return $this->hasStatusHelper(OrderStatus::CODE_NOT_PAID);
        if ($exact) {
            return $this->hasStatusHelper(self::CODE_NOT_PAID);
        }

        return !$this->isPaid(false);
    }

    /**
     * Check if the current status  is PAID.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_PAID.
     *                    if false, it will check if the order has been paid, whatever the exact status is. The default is true.
     *
     * @return bool true if PAID, false otherwise
     */
    public function isPaid(bool $exact = true): bool
    {
        return $this->hasStatusHelper(
            $exact ?
            self::CODE_PAID :
            [self::CODE_PAID, self::CODE_PROCESSING, self::CODE_SENT],
        );
    }

    /**
     * Check if the current status is PROCESSING.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_PROCESSING.
     *                    if false, it will check if the order is processing, whatever the exact status is. The default is true.
     *
     * @return bool true if PROCESSING, false otherwise
     */
    public function isProcessing(bool $exact = true): bool
    {
        return $this->hasStatusHelper(self::CODE_PROCESSING);
    }

    /**
     * Check if the current status is SENT.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_SENT.
     *                    if false, it will check if the order is send, whatever the exact status is. The default is true.
     *
     * @return bool true if SENT, false otherwise
     */
    public function isSent(bool $exact = true): bool
    {
        return $this->hasStatusHelper(self::CODE_SENT);
    }

    /**
     * Check if the current status is CANCELED.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_CANCELED.
     *                    if false, it will check if the order is canceled, whatever the exact status is. The default is true.
     *
     * @return bool true if CANCELED, false otherwise
     */
    public function isCancelled(bool $exact = true): bool
    {
        return $this->hasStatusHelper(self::CODE_CANCELED);
    }

    /**
     * Check if the current status is REFUNDED.
     *
     * @param bool $exact if true, the method will check if the current status is exactly OrderStatus::CODE_CANCELED.
     *                    if false, it will check if the order is canceled, whatever the exact status is. The default is true.
     *
     * @return bool true if REFUNDED, false otherwise
     */
    public function isRefunded(bool $exact = true): bool
    {
        return $this->hasStatusHelper(self::CODE_REFUNDED);
    }

    /**
     * Check if the current status is $statusCode or, if $statusCode is an array, if the current
     * status is in the $statusCode array.
     *
     * @param string|array $statusCode the status code, one of OrderStatus::CODE_xxx constants
     *
     * @return bool true if the current status is in the provided status, false otherwise
     */
    public function hasStatusHelper(string|array $statusCode): bool
    {
        if (\is_array($statusCode)) {
            return \in_array($this->getCode(), $statusCode, true);
        }

        return $this->getCode() === $statusCode;
    }
}
