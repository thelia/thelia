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
 * Raised as ORDER_PAYMENT_TRANSACTION_RECORDED once a line of the payment journal is
 * written, and again when a pending line is settled.
 */
class OrderPaymentTransactionEvent extends ActionEvent
{
    public function __construct(
        protected Order $order,
        protected OrderPaymentTransaction $transaction,
        protected ?string $sourceModuleCode = null,
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

    public function getSourceModuleCode(): ?string
    {
        return $this->sourceModuleCode;
    }
}
