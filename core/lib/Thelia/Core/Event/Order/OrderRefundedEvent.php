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

namespace Thelia\Core\Event\Order;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Payment\Enum\RefundReason;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;

/**
 * Dispatched as ORDER_REFUNDED once money was given back — through the provider, or recorded
 * by hand — for credit note and accounting modules to follow.
 */
class OrderRefundedEvent extends ActionEvent
{
    public function __construct(
        protected Order $order,
        protected OrderPaymentTransaction $transaction,
        protected RefundReason $reason,
        protected ?string $comment,
        protected bool $offline,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getTransaction(): OrderPaymentTransaction
    {
        return $this->transaction;
    }

    public function getReason(): RefundReason
    {
        return $this->reason;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /** Whether the money left outside the payment provider, recorded by hand. */
    public function isOffline(): bool
    {
        return $this->offline;
    }
}
