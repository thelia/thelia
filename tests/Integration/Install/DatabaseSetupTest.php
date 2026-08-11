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

namespace Thelia\Tests\Integration\Install;

use Thelia\Core\TheliaKernel;
use Thelia\Install\Standalone\DatabaseSetup;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\Version\Version;

final class DatabaseSetupTest extends IntegrationTestCase
{
    // This suite issues DDL (CREATE DATABASE), which would wait on the schema metadata
    // lock held by the base class' per-test transaction until lock_wait_timeout (~1 year).
    // DDL tests must opt out of the transactional isolation, per IntegrationTestCase.
    protected bool $useTransaction = false;

    public function testConstructorRejectsInvalidDatabaseName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database name');

        new DatabaseSetup('db', '3306', 'DROP DATABASE test; --', 'db', 'db');
    }

    public function testConstructorAcceptsValidDatabaseName(): void
    {
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
        self::assertInstanceOf(DatabaseSetup::class, $setup);
    }

    public function testConnectSucceedsWithTestCredentials(): void
    {
        $setup = $this->createDatabaseSetup();
        $setup->connect();

        self::assertInstanceOf(\PDO::class, $setup->getPdo());
    }

    public function testGetWarningsIsEmptyByDefault(): void
    {
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
        self::assertSame([], $setup->getWarnings());
    }

    public function testCreateDatabaseIsIdempotent(): void
    {
        // Creating a database that already exists should not throw.
        $setup = $this->createDatabaseSetup();
        $setup->createDatabase();

        // If we get here, it didn't throw.
        self::assertTrue(true);
    }

    public function testSeededVersionIsTheVersionOfTheRunningCode(): void
    {
        // This database is built by bin/test-prepare through DatabaseSetup, exactly like a
        // fresh install. The version it carries must be the code version: any older value
        // makes setup/update.php replay the update scripts above it (#3571).
        $parsedVersion = Version::parse(TheliaKernel::THELIA_VERSION);

        // Reading past the config cache: that pool outlives a database rebuild, so the
        // cached copy would answer for a row this test is precisely about.
        self::assertSame($parsedVersion['version'], ConfigQuery::read('thelia_version', null, true));
        self::assertSame($parsedVersion['major'], ConfigQuery::read('thelia_major_version', null, true));
        self::assertSame($parsedVersion['minus'], ConfigQuery::read('thelia_minus_version', null, true));
        self::assertSame($parsedVersion['release'], ConfigQuery::read('thelia_release_version', null, true));
        self::assertSame($parsedVersion['extra'], ConfigQuery::read('thelia_extra_version', null, true));
    }

    /**
     * Builds a DatabaseSetup pointing at the configured test database. Credentials come from
     * the environment ($_SERVER, populated from .env.test.local by the test bootstrap), so the
     * suite connects to the CI MySQL (127.0.0.1) as well as a local DDEV database (db), instead
     * of a hardcoded host that does not resolve on the CI runner.
     */
    private function createDatabaseSetup(): DatabaseSetup
    {
        return new DatabaseSetup(
            (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
            (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
        );
    }
}
