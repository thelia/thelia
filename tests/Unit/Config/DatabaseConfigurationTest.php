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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Thelia\Config\DatabaseConfiguration;

/**
 * The database.yml file describes either one connection or a list of named
 * connections, the Thelia one among them.
 */
final class DatabaseConfigurationTest extends TestCase
{
    public function testASingleConnectionIsCompletedWithTheDefaultSettings(): void
    {
        $configuration = $this->process(['connection' => ['dsn' => 'mysql:host=db.example.org;dbname=shop']]);

        self::assertSameSettings(
            [
                'dsn' => 'mysql:host=db.example.org;dbname=shop',
                'driver' => 'mysql',
                'user' => 'root',
                'password' => '',
                'classname' => ConnectionWrapper::class,
            ],
            $configuration['connection'],
        );
    }

    public function testAConnectionWithAnEmptyDsnIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "database.connection.dsn" cannot contain an empty value');

        $this->process(['connection' => ['dsn' => '']]);
    }

    public function testNamedConnectionsAreReadWhenTheTheliaOneIsAmongThem(): void
    {
        $configuration = $this->process([
            'connections' => [
                DatabaseConfiguration::THELIA_CONNECTION_NAME => [
                    'dsn' => 'mysql:host=main.example.org;dbname=shop',
                    'user' => 'shop',
                ],
                'Reporting' => [
                    'dsn' => 'mysql:host=replica.example.org;dbname=shop',
                    'password' => 'secret',
                ],
            ],
        ]);

        self::assertSame(
            [DatabaseConfiguration::THELIA_CONNECTION_NAME, 'Reporting'],
            array_keys($configuration['connections']),
        );
        self::assertSameSettings(
            [
                'dsn' => 'mysql:host=main.example.org;dbname=shop',
                'user' => 'shop',
                'driver' => 'mysql',
                'password' => '',
                'classname' => ConnectionWrapper::class,
            ],
            $configuration['connections'][DatabaseConfiguration::THELIA_CONNECTION_NAME],
        );
        self::assertSameSettings(
            [
                'dsn' => 'mysql:host=replica.example.org;dbname=shop',
                'password' => 'secret',
                'driver' => 'mysql',
                'user' => 'root',
                'classname' => ConnectionWrapper::class,
            ],
            $configuration['connections']['Reporting'],
        );
        self::assertArrayNotHasKey('connection', $configuration);
    }

    public function testNamedConnectionsWithoutTheTheliaOneAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "database.connections": The \'TheliaMain\' connection must be defined.',
        );

        $this->process(['connections' => ['Reporting' => ['dsn' => 'mysql:host=replica.example.org;dbname=shop']]]);
    }

    public function testASingleConnectionAndNamedConnectionsCannotBeGivenTogether(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "database": The \'database\' node must contain either a \'connection\' node or a \'connections\' node, but not both.',
        );

        $this->process([
            'connection' => ['dsn' => 'mysql:host=db.example.org;dbname=shop'],
            'connections' => [
                DatabaseConfiguration::THELIA_CONNECTION_NAME => ['dsn' => 'mysql:host=main.example.org;dbname=shop'],
            ],
        ]);
    }

    public function testADatabaseNodeWithoutAnyConnectionIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("The 'database' node must contain a 'connection' node or a 'connections' node.");

        $this->process([]);
    }

    public function testAConnectionNodeCanBeBuiltOnItsOwn(): void
    {
        $node = (new DatabaseConfiguration())->buildConnectionNode('replica', false)->getNode(true);

        self::assertSame('replica', $node->getName());
        self::assertSameSettings(
            ['dsn' => 'mysql:host=replica.example.org', 'driver' => 'mysql', 'user' => 'root', 'password' => '', 'classname' => ConnectionWrapper::class],
            $node->finalize($node->normalize(['dsn' => 'mysql:host=replica.example.org'])),
        );
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private static function assertSameSettings(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @param array<string, mixed> $database
     *
     * @return array<string, mixed>
     */
    private function process(array $database): array
    {
        return (new Processor())->processConfiguration(new DatabaseConfiguration(), ['database' => $database]);
    }
}
