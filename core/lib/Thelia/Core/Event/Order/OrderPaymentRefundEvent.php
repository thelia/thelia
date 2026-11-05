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
 * Dispatched as ORDER_PAYMENT_REFUND to give back all or part of what an order's payment
 * collected: through the payment module, or recorded by hand when $offline. A null amount
 * gives back everything still refundable. The core listener sets the journal line on the event.
 */
class OrderPaymentRefundEvent extends ActionEvent
{
    protected ?OrderPaymentTransaction $transaction = null;

    public function __construct(
        protected Order $order,
        protected ?float $amount,
        protected RefundReason $reason,
        protected ?string $comment = null,
        protected bool $offline = false,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getReason(): RefundReason
    {
        return $this->reason;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function isOffline(): bool
    {
        return $this->offline;
    }

    public function setTransaction(OrderPaymentTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getTransaction(): OrderPaymentTransaction
    {
        if (null === $this->transaction) {
            throw new \LogicException('No refund has been recorded on this event yet.');
        }

        return $this->transaction;
    }
}
