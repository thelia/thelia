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
 * Adds up the payment journal of an order in one query, on the database side.
 */
final readonly class PaymentTransactionTotalsReader
{
    public function forOrder(int $orderId, ?ConnectionInterface $connection = null): PaymentTransactionTotals
    {
        $connection ??= Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME);

        $statement = $connection->prepare(\sprintf(
            'SELECT `type`, SUM(`amount`) AS `total` FROM `%s` WHERE `order_id` = :order_id AND `state` = :state GROUP BY `type`',
            OrderPaymentTransactionTableMap::TABLE_NAME,
        ));
        $statement->bindValue(':order_id', $orderId, \PDO::PARAM_INT);
        $statement->bindValue(':state', PaymentTransactionState::SUCCEEDED->value, \PDO::PARAM_STR);
        $statement->execute();

        $zero = PaymentAmount::normalize(0);
        $totals = array_fill_keys(array_map(static fn (PaymentTransactionType $type): string => $type->value, PaymentTransactionType::cases()), $zero);

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $totals[(string) $row['type']] = PaymentAmount::normalize((string) $row['total']);
        }

        return new PaymentTransactionTotals(
            authorized: $totals[PaymentTransactionType::AUTHORIZATION->value],
            captured: $totals[PaymentTransactionType::CAPTURE->value],
            voided: $totals[PaymentTransactionType::VOID->value],
            refunded: $totals[PaymentTransactionType::REFUND->value],
        );
    }
}
