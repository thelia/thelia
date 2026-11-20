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

namespace Thelia\Domain\Company\Checkout\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderStatus;

/**
 * What the orders of a company cost, in the default currency, read by the database.
 *
 * The amount of an order is the one the invoice states ({@see OrderAmountSql}, which mirrors
 * Order::getTotalAmount()): taxed goods less the discount plus the postage, then divided by the
 * rate the order was placed at. A period is summed by one statement, never by walking the orders.
 */
final readonly class CompanySpendingReader
{
    /**
     * What the orders the buyer placed for the company since the given moment cost, the ones
     * cancelled or refunded left out and, when it is named, the order being written.
     *
     * @return string a DECIMAL(16,6)
     */
    public function spentSince(int $companyId, int $customerId, \DateTimeImmutable $since, ?int $exceptOrderId = null, ?ConnectionInterface $connection = null): string
    {
        $where = 'o.company_id = :company_id AND o.placed_by_customer_id = :customer_id AND o.created_at >= :since'
            .' AND order_status.code NOT IN (\''.OrderStatus::CODE_CANCELED.'\', \''.OrderStatus::CODE_REFUNDED.'\')'
            .(null === $exceptOrderId ? '' : ' AND o.id <> :except_order_id');

        $parameters = [
            'company_id' => [$companyId, \PDO::PARAM_INT],
            'customer_id' => [$customerId, \PDO::PARAM_INT],
            'since' => [$since->format('Y-m-d H:i:s'), \PDO::PARAM_STR],
        ];

        if (null !== $exceptOrderId) {
            $parameters['except_order_id'] = [$exceptOrderId, \PDO::PARAM_INT];
        }

        return $this->read($where, $parameters, $connection, true);
    }

    /**
     * What one order costs, whatever its status.
     *
     * @return string a DECIMAL(16,6)
     */
    public function totalOfOrder(int $orderId, ?ConnectionInterface $connection = null): string
    {
        return $this->read('o.id = :order_id', ['order_id' => [$orderId, \PDO::PARAM_INT]], $connection, false);
    }

    /**
     * @param array<string, array{int|string, int}> $parameters
     */
    private function read(string $where, array $parameters, ?ConnectionInterface $connection, bool $withStatus): string
    {
        $connection ??= Propel::getConnection(OrderTableMap::DATABASE_NAME);

        $sql = 'WITH placed_order AS (
                SELECT
                    o.id,
                    o.discount,
                    o.postage,
                    o.currency_rate,
                    '.OrderAmountSql::itemsTaxedTotal('o').' AS items_total
                FROM `order` AS o'.($withStatus ? '
                INNER JOIN order_status ON order_status.id = o.status_id' : '').'
                WHERE '.$where.'
            )
            SELECT CAST(COALESCE(SUM(
                CAST('.OrderAmountSql::orderTotal('placed_order', 'placed_order.items_total').' AS DECIMAL(16,6))
                / CASE WHEN placed_order.currency_rate > 0 THEN placed_order.currency_rate ELSE 1 END
            ), 0) AS DECIMAL(16,6)) AS spent
            FROM placed_order';

        $statement = $connection->prepare($sql);

        foreach ($parameters as $name => [$value, $type]) {
            $statement->bindValue($name, $value, $type);
        }

        OrderAmountSql::bindRoundingPivots($statement, $sql);
        $statement->execute();

        $spent = $statement->fetchColumn();

        return false === $spent || null === $spent || '' === $spent ? '0.000000' : (string) $spent;
    }
}
