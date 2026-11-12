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

namespace Thelia\Domain\Payment\Exception;

use Thelia\Model\OrderPaymentTransaction;

/**
 * The line is written, and stays: what failed is a listener of
 * ORDER_PAYMENT_TRANSACTION_RECORDED, the order move or whatever a module hangs on it.
 *
 * Not a PaymentException: nothing was refused. A notification that ends on it is to be
 * answered as failed, so that the provider replays it and the listeners run again; the
 * shop's own capture keeps the line and logs the failure.
 */
final class PaymentAnnouncementFailedException extends \RuntimeException
{
    public function __construct(
        private readonly OrderPaymentTransaction $transaction,
        \Throwable $previous,
    ) {
        parent::__construct(\sprintf('Payment transaction #%d is recorded, but a listener of its announcement failed: %s', (int) $transaction->getId(), $previous->getMessage()), 0, $previous);
    }

    public function getTransaction(): OrderPaymentTransaction
    {
        return $this->transaction;
    }
}
