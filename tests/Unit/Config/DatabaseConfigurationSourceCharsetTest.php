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

namespace Thelia\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Config\DatabaseConfigurationSource;

/**
 * The character set of a connection belongs in its DSN: pdo_mysql applies it
 * while the connection is being opened, where it costs nothing, instead of the
 * round trip a SET NAMES pays on every connection.
 */
final class DatabaseConfigurationSourceCharsetTest extends TestCase
{
    #[Test]
    public function theDsnBuiltFromTheEnvironmentNamesTheCharacterSet(): void
    {
        $connections = $this->sourceFromEnvironment()
            ->getPropelConnectionsConfiguration()['propel']['database']['connections'];

        self::assertSame(
            'mysql:host=db.example.org;dbname=shop;port=3306;charset=utf8mb4',
            $connections[DatabaseConfiguration::THELIA_CONNECTION_NAME]['dsn'],
        );
    }

    #[Test]
    public function noQueryIsRunToOpenAConnection(): void
    {
        $connection = $this->sourceFromEnvironment()
            ->getPropelConnectionsConfiguration()['propel']['database']['connections'][DatabaseConfiguration::THELIA_CONNECTION_NAME];

        self::assertArrayNotHasKey(
            'settings',
            $connection,
            'A connection must not carry an initialisation query any more.',
        );
    }

    #[Test]
    public function aDsnNamingItsOwnCharacterSetKeepsIt(): void
    {
        self::assertSame(
            'mysql:host=localhost;dbname=shop;charset=latin1',
            DatabaseConfigurationSource::withCharset('mysql:host=localhost;dbname=shop;charset=latin1'),
        );
        self::assertSame(
            'mysql:host=localhost;dbname=shop;CHARSET=Latin1',
            DatabaseConfigurationSource::withCharset('mysql:host=localhost;dbname=shop;CHARSET=Latin1'),
        );
    }

    #[Test]
    public function aDsnOfAnotherDriverIsLeftAlone(): void
    {
        self::assertSame(
            'pgsql:host=localhost;dbname=shop',
            DatabaseConfigurationSource::withCharset('pgsql:host=localhost;dbname=shop'),
        );
    }

    #[Test]
    public function aTrailingSemicolonDoesNotProduceAnEmptyParameter(): void
    {
        self::assertSame(
            'mysql:host=localhost;dbname=shop;charset=utf8mb4',
            DatabaseConfigurationSource::withCharset('mysql:host=localhost;dbname=shop;'),
        );
    }

    private function sourceFromEnvironment(): DatabaseConfigurationSource
    {
        return new DatabaseConfigurationSource([
            'kernel.environment' => 'test',
            'thelia.database_host' => 'db.example.org',
            'thelia.database_name' => 'shop',
            'thelia.database_port' => '3306',
            'thelia.database_user' => 'shop',
            'thelia.database_password' => 'secret',
        ]);
    }
}
