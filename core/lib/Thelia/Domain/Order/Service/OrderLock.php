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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Log\Tlog;

/**
 * A named database lock, held by the connection that writes, to serialize a check and
 * the write that depends on it across workers: two notifications of the same event, two
 * submissions of the same capture.
 *
 * The server releases the lock when the connection goes, so nothing leaks when a worker
 * dies. MariaDB and MySQL count a lock taken twice by the same connection, so a caller
 * holding it can call code that takes it again.
 */
final readonly class OrderLock
{
    /**
     * MySQL refuses a user lock name longer than 64 bytes; MariaDB accepts 192. The
     * shorter of the two is the one a name has to fit.
     */
    public const NAME_MAX_LENGTH = 64;

    /**
     * Returns false rather than throwing when the lock cannot be had within the timeout:
     * whether to go on unchecked or to refuse is the caller's decision.
     */
    public function acquire(ConnectionInterface $connection, string $lockName, int $timeoutSeconds): bool
    {
        try {
            $statement = $connection->prepare('SELECT GET_LOCK(?, ?)');
            $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
            $statement->bindValue(2, $timeoutSeconds, \PDO::PARAM_INT);
            $statement->execute();

            // 1 when granted, 0 when the wait ran out, NULL on a server-side error.
            return '1' === (string) $statement->fetchColumn();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(
                'Lock {lock} could not be requested: {ex}',
                ['lock' => $lockName, 'ex' => $throwable->getMessage()],
            );

            return false;
        }
    }

    public function release(ConnectionInterface $connection, string $lockName): void
    {
        try {
            $statement = $connection->prepare('SELECT RELEASE_LOCK(?)');
            $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\Throwable $throwable) {
            // The server drops the lock when the connection goes, which is the only way
            // this statement fails: nothing is leaked, but the failure is worth knowing.
            Tlog::getInstance()->warning(
                'Lock {lock} could not be released: {ex}',
                ['lock' => $lockName, 'ex' => $throwable->getMessage()],
            );
        }
    }
}
