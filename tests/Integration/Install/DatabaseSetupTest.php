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
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
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
        $setup = new DatabaseSetup('db', '3306', 'test', 'db', 'db');
        $setup->createDatabase();

        // If we get here, it didn't throw.
        self::assertTrue(true);
    }
}
