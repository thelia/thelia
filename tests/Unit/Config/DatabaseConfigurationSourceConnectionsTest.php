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

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Config\DatabaseConfigurationSource;

/**
 * The DATABASE_* environment variables describe the Thelia connection only when
 * they name both a host and a database; the connection then reaches Propel with
 * every setting it needs to open it.
 */
final class DatabaseConfigurationSourceConnectionsTest extends TestCase
{
    public function testTheEnvironmentConnectionReachesPropelWithEverySetting(): void
    {
        $source = new DatabaseConfigurationSource([
            'kernel.environment' => 'prod',
            'thelia.database_host' => 'db.example.org',
            'thelia.database_name' => 'shop',
            'thelia.database_port' => '3306',
            'thelia.database_user' => 'shop_user',
            'thelia.database_password' => 'secret',
        ]);

        self::assertSame(
            [
                'propel' => [
                    'database' => [
                        'connections' => [
                            DatabaseConfiguration::THELIA_CONNECTION_NAME => [
                                'adapter' => 'mysql',
                                'dsn' => 'mysql:host=db.example.org;dbname=shop;port=3306;charset=utf8mb4',
                                'user' => 'shop_user',
                                'password' => 'secret',
                                'classname' => ConnectionWrapper::class,
                            ],
                        ],
                    ],
                    'runtime' => [
                        'defaultConnection' => DatabaseConfiguration::THELIA_CONNECTION_NAME,
                    ],
                ],
            ],
            $source->getPropelConnectionsConfiguration(),
        );
    }

    public function testAHostWithoutADatabaseNameIsNotTakenForAConnection(): void
    {
        $environment = 'unit-'.bin2hex(random_bytes(4));

        if (is_file(THELIA_CONF_DIR.'database.yml') || is_file(THELIA_CONF_DIR.'database_'.$environment.'.yml')) {
            self::markTestSkipped('This installation describes its connection in a database.yml.');
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No database connection found');

        new DatabaseConfigurationSource([
            'kernel.environment' => $environment,
            'thelia.database_host' => 'db.example.org',
            'thelia.database_name' => null,
            'thelia.database_port' => '3306',
            'thelia.database_user' => 'shop_user',
            'thelia.database_password' => 'secret',
        ]);
    }
}
