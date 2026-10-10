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
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;

/**
 * Dispatched as ORDER_PAYMENT_CAPTURE to take all or part of what an order's
 * authorization still holds. A null amount captures the whole remainder. The core
 * listener sets the journal line it wrote on the event.
 */
class OrderPaymentCaptureEvent extends ActionEvent
{
    protected ?OrderPaymentTransaction $transaction = null;

    public function __construct(
        protected Order $order,
        protected ?float $amount = null,
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

    public function setTransaction(OrderPaymentTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getTransaction(): OrderPaymentTransaction
    {
        if (null === $this->transaction) {
            throw new \LogicException('No capture has been recorded on this event yet.');
        }

        return $this->transaction;
    }

    public function hasTransaction(): bool
    {
        return null !== $this->transaction;
    }
}
