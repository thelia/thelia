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

namespace Thelia\Domain\Shared\Database;

/**
 * Tells whether a failure is a lock conflict the database broke: a deadlock (1213), or on
 * MariaDB 11.6 and later a row changed since the transaction took its picture (1020).
 */
final class LockConflictDetector
{
    private const int ER_CHECKREAD = 1020;
    private const int ER_LOCK_DEADLOCK = 1213;

    /**
     * Wherever Propel wrapped the driver exception.
     */
    public static function isLockConflict(\Throwable $failure): bool
    {
        for ($current = $failure; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException
                && \in_array((int) ($current->errorInfo[1] ?? 0), [self::ER_LOCK_DEADLOCK, self::ER_CHECKREAD], true)) {
                return true;
            }
        }

        return false;
    }
}
