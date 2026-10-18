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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Log\Tlog;

/**
 * Calls a lifecycle method of a module inside the transaction the core opened for it, and
 * hands that transaction back the way the core left it, whatever the module did with it.
 *
 * MySQL and MariaDB commit the open transaction before a schema change: an ALTER TABLE in
 * install(), update(), postActivation() or destroy() ends it. Propel does not see it and keeps
 * counting the transaction. While its counter is above zero, beginTransaction() sends nothing
 * to the database, and commit() and rollBack() do nothing without a database transaction, so
 * the counter never comes down: every later transaction of the process commits each statement
 * on its own and rolls nothing back.
 *
 * A module that opens its own transaction around the schema change, or a script whose next
 * statement fails, leaves the counter off by one more level. The counter and the database are
 * put back in line with the level found on entry, on success as on failure, and the exception
 * of the module goes up untouched, for the caller to roll back what follows the schema change.
 */
final class ModuleTransactionGuard
{
    /**
     * @template T
     *
     * @param callable(): T $moduleCall
     *
     * @return T
     */
    public static function run(ConnectionInterface $connection, string $moduleCode, string $method, callable $moduleCall): mixed
    {
        if (!$connection instanceof ConnectionWrapper) {
            return $moduleCall();
        }

        $levelOnEntry = $connection->getNestedTransactionCount();

        try {
            $result = $moduleCall();
        } catch (\Throwable $moduleFailure) {
            try {
                self::restoreLevel($connection, $levelOnEntry, $moduleCode, $method, false);
            } catch (\Throwable $restoreFailure) {
                // The failure of the module is the one to report: a connection that could
                // not be put back is logged, never thrown in its place.
                Tlog::getInstance()->error(\sprintf(
                    'The database transaction could not be restored after the failure of %s() in module %s: %s',
                    $method,
                    $moduleCode,
                    $restoreFailure->getMessage(),
                ));
            }

            throw $moduleFailure;
        }

        self::restoreLevel($connection, $levelOnEntry, $moduleCode, $method, true);

        return $result;
    }

    private static function restoreLevel(
        ConnectionWrapper $connection,
        int $levelOnEntry,
        string $moduleCode,
        string $method,
        bool $moduleSucceeded,
    ): void {
        if ($connection->isInTransaction() && !$connection->inTransaction()) {
            Tlog::getInstance()->warning(\sprintf(
                'Module %s ended the database transaction of its %s(), most likely with a schema change: what ran before it is committed.%s',
                $moduleCode,
                $method,
                'update' === $method ? ' Run schema changes from postUpdate(), called once the transaction is committed.' : '',
            ));

            // A transaction opened on the wrapped connection puts the database back in line
            // with the counter. forceRollBack() would throw instead: the database has no
            // transaction left to roll back.
            $connection->getWrappedConnection()?->beginTransaction();
        }

        // Levels the module opened and left open. commit() and rollBack() only count them
        // down while the database has a transaction, hence the second condition.
        while ($connection->getNestedTransactionCount() > $levelOnEntry && $connection->inTransaction()) {
            $moduleSucceeded ? $connection->commit() : $connection->rollBack();
        }

        // Levels the module closed without opening them.
        while ($connection->getNestedTransactionCount() < $levelOnEntry) {
            $connection->beginTransaction();
        }
    }
}
