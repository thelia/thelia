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
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Model\OrderPaymentTransaction;

/**
 * Dispatched as ORDER_PAYMENT_TRANSACTION_SETTLE to record by hand the outcome of a
 * pending line the provider never confirmed, as the merchant reads it in the provider's
 * own back office. The core listener sets the settled line on the event.
 */
class OrderPaymentSettlementEvent extends ActionEvent
{
    public function __construct(
        protected OrderPaymentTransaction $transaction,
        protected PaymentTransactionState $state,
        protected ?string $pspReference = null,
        protected ?string $note = null,
    ) {
    }

    public function getTransaction(): OrderPaymentTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(OrderPaymentTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getState(): PaymentTransactionState
    {
        return $this->state;
    }

    public function getPspReference(): ?string
    {
        return $this->pspReference;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
