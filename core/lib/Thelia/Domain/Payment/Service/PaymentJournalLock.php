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

namespace Thelia\Domain\Payment\Service;

use Propel\Runtime\Propel;
use Thelia\Domain\Order\Service\OrderLock;
use Thelia\Domain\Payment\Exception\PaymentJournalBusyException;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;

/**
 * Serializes everything that reads the payment journal of an order and writes to it on
 * the strength of what it read: a capture against what the authorization holds, a refund
 * against what was taken, a replayed notification against the line it already wrote.
 *
 * Unlike the order history, the lock is insisted on: a journal written unchecked is money
 * taken twice. A worker that does not get it in time writes nothing and is told so, and
 * the provider, or the merchant, tries again.
 *
 * The lock is taken again by the same connection without waiting, so code holding it can
 * call code that takes it.
 */
final readonly class PaymentJournalLock
{
    private const NAME_PREFIX = 'thelia_order_payment:';

    /**
     * How long a worker waits, in seconds, for the worker already writing the journal of
     * the same order. A provider call is not made under the lock, so the wait only ever
     * covers a few queries.
     */
    private const TIMEOUT = 5;

    public function __construct(
        private OrderLock $orderLock,
    ) {
    }

    public static function nameFor(int $orderId): string
    {
        return self::NAME_PREFIX.$orderId;
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws PaymentJournalBusyException when another worker holds the journal for too long
     */
    public function withOrder(int $orderId, callable $work): mixed
    {
        $connection = Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME);
        $lockName = self::nameFor($orderId);

        if (!$this->orderLock->acquire($connection, $lockName, self::TIMEOUT)) {
            throw new PaymentJournalBusyException(\sprintf('The payment journal of order #%d is being written by another process. Try again in a moment.', $orderId));
        }

        try {
            return $work();
        } finally {
            $this->orderLock->release($connection, $lockName);
        }
    }
}
