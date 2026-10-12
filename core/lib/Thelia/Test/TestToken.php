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

namespace Thelia\Test;

/**
 * The number of a test process that runs next to others, read from the
 * TEST_TOKEN environment variable (Infection and ParaTest set it, from 1).
 *
 * Each process then works on its own database, container cache, Propel models
 * and lock directory, so that two processes running the suites at the same
 * time never write into each other's state. Without the variable, nothing
 * changes: one process, the "test" database, the usual directories.
 *
 *     TEST_TOKEN=2 composer test:prepare      # creates the database test_2
 *     TEST_TOKEN=2 composer test:integration  # runs against test_2
 */
final class TestToken
{
    public static function current(): ?string
    {
        $token = getenv('TEST_TOKEN');

        if (false === $token || '' === $token) {
            $token = $_SERVER['TEST_TOKEN'] ?? '';
        }

        $token = (string) $token;

        return '' !== $token && ctype_digit($token) ? $token : null;
    }

    /**
     * Points DATABASE_NAME at the database of this process (test_2 for the
     * second one). Idempotent: the test bootstrap, bin/test-prepare and the
     * kernel of the test environment all call it, whichever runs first.
     */
    public static function useOwnDatabase(): void
    {
        $suffix = self::suffix();
        $databaseName = (string) ($_SERVER['DATABASE_NAME'] ?? $_ENV['DATABASE_NAME'] ?? getenv('DATABASE_NAME') ?: '');

        if ('' === $suffix || '' === $databaseName || str_ends_with($databaseName, $suffix)) {
            return;
        }

        $databaseName .= $suffix;
        $_SERVER['DATABASE_NAME'] = $_ENV['DATABASE_NAME'] = $databaseName;
        putenv('DATABASE_NAME='.$databaseName);
    }

    /**
     * "_2" for the second process, "" without a token: appended to the name of
     * a database or of a directory the processes would otherwise share.
     */
    public static function suffix(): string
    {
        $token = self::current();

        return null === $token ? '' : '_'.$token;
    }
}
