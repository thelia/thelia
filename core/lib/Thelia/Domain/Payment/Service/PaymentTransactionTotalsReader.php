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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Payment\DTO\PaymentTransactionTotals;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;

/**
 * Adds up the payment journal of an order in one query, on the database side: the
 * succeeded lines, and the pending ones apart, since they reserve what they asked for.
 */
final readonly class PaymentTransactionTotalsReader
{
    public function forOrder(int $orderId, ?ConnectionInterface $connection = null): PaymentTransactionTotals
    {
        $connection ??= Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME);

        $statement = $connection->prepare(\sprintf(
            'SELECT `type`, `state`, SUM(`amount`) AS `total` FROM `%s` WHERE `order_id` = :order_id AND `state` IN (:succeeded, :pending) GROUP BY `type`, `state`',
            OrderPaymentTransactionTableMap::TABLE_NAME,
        ));
        $statement->bindValue(':order_id', $orderId, \PDO::PARAM_INT);
        $statement->bindValue(':succeeded', PaymentTransactionState::SUCCEEDED->value, \PDO::PARAM_STR);
        $statement->bindValue(':pending', PaymentTransactionState::PENDING->value, \PDO::PARAM_STR);
        $statement->execute();

        $totals = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $totals[$row['state'].':'.$row['type']] = PaymentAmount::normalize((string) $row['total']);
        }

        $sum = static fn (PaymentTransactionState $state, PaymentTransactionType $type): string => $totals[$state->value.':'.$type->value] ?? PaymentAmount::normalize(0);

        return new PaymentTransactionTotals(
            authorized: $sum(PaymentTransactionState::SUCCEEDED, PaymentTransactionType::AUTHORIZATION),
            captured: $sum(PaymentTransactionState::SUCCEEDED, PaymentTransactionType::CAPTURE),
            voided: $sum(PaymentTransactionState::SUCCEEDED, PaymentTransactionType::VOID),
            refunded: $sum(PaymentTransactionState::SUCCEEDED, PaymentTransactionType::REFUND),
            pendingCapture: $sum(PaymentTransactionState::PENDING, PaymentTransactionType::CAPTURE),
            pendingVoid: $sum(PaymentTransactionState::PENDING, PaymentTransactionType::VOID),
            pendingRefund: $sum(PaymentTransactionState::PENDING, PaymentTransactionType::REFUND),
        );
    }
}
