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

use Thelia\Install\Standalone\DatabaseSetup;
use Thelia\Test\IntegrationTestCase;

final class DatabaseSetupTest extends IntegrationTestCase
{
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
