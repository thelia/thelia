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

namespace Thelia\Domain\Sequence;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Allocates strictly consecutive numbers from a named counter stored in the
 * order_sequence table.
 *
 * The increment is a single atomic UPDATE executed on the caller's connection.
 * When the caller runs inside a transaction, the counter row stays locked until
 * commit and the increment is rolled back with everything else — numbers are
 * gapless by construction. Callers should therefore allocate as late as
 * possible in their transaction to keep the serialization window short.
 */
final readonly class GaplessSequenceGenerator
{
    public function next(string $sequenceName, ConnectionInterface $connection): int
    {
        $insert = $connection->prepare(
            'INSERT IGNORE INTO `order_sequence` (`name`, `current_value`) VALUES (:name, 0)'
        );
        $insert->bindValue(':name', $sequenceName, \PDO::PARAM_STR);
        $insert->execute();

        // LAST_INSERT_ID(expr) makes the increment and its read a single
        // atomic statement, safe both inside and outside a transaction.
        $update = $connection->prepare(
            'UPDATE `order_sequence` SET `current_value` = LAST_INSERT_ID(`current_value` + 1) WHERE `name` = :name'
        );
        $update->bindValue(':name', $sequenceName, \PDO::PARAM_STR);
        $update->execute();

        return (int) $connection->lastInsertId();
    }

    /**
     * Force the counter to a given value, e.g. when taking over an externally
     * managed numbering series. The next allocated number will be value + 1.
     */
    public function set(string $sequenceName, int $value, ConnectionInterface $connection): void
    {
        $statement = $connection->prepare(
            'INSERT INTO `order_sequence` (`name`, `current_value`) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE `current_value` = :value'
        );
        $statement->bindValue(':name', $sequenceName, \PDO::PARAM_STR);
        $statement->bindValue(':value', $value, \PDO::PARAM_INT);
        $statement->execute();
    }
}
