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

namespace Thelia\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\PDODataFetcher;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\TheliaKernel;

/**
 * The database host is what tells an installed shop: without it the shop is not
 * installed, whatever connection Propel may already hold.
 */
final class TheliaKernelTest extends TestCase
{
    private ServiceContainerInterface $propelServiceContainer;

    private ?bool $installed;

    /** @var array{server: mixed, env: mixed, process: string|false} */
    private array $databaseHost;

    protected function setUp(): void
    {
        $this->propelServiceContainer = Propel::getServiceContainer();
        Propel::setServiceContainer(new StandardServiceContainer());

        $this->installed = $this->installedProperty()->getValue();
        $this->installedProperty()->setValue(null, null);

        $this->databaseHost = [
            'server' => $_SERVER['DATABASE_HOST'] ?? null,
            'env' => $_ENV['DATABASE_HOST'] ?? null,
            'process' => getenv('DATABASE_HOST'),
        ];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['DATABASE_HOST'], $_ENV['DATABASE_HOST']);

        if (null !== $this->databaseHost['server']) {
            $_SERVER['DATABASE_HOST'] = $this->databaseHost['server'];
        }

        if (null !== $this->databaseHost['env']) {
            $_ENV['DATABASE_HOST'] = $this->databaseHost['env'];
        }

        putenv(false === $this->databaseHost['process'] ? 'DATABASE_HOST' : 'DATABASE_HOST='.$this->databaseHost['process']);

        $this->installedProperty()->setValue(null, $this->installed);
        Propel::setServiceContainer($this->propelServiceContainer);
    }

    public function testAShopWithoutADatabaseHostIsNotInstalledEvenWhenPropelHoldsAConfiguredConnection(): void
    {
        $_SERVER['DATABASE_HOST'] = $_ENV['DATABASE_HOST'] = '';
        putenv('DATABASE_HOST=');

        $result = $this->createStub(PDODataFetcher::class);
        $result->method('fetch')->willReturn(['id' => 1]);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($result);

        $serviceContainer = Propel::getServiceContainer();
        \assert($serviceContainer instanceof StandardServiceContainer);
        $serviceContainer->setAdapterClass(DatabaseConfiguration::THELIA_CONNECTION_NAME, 'mysql');
        $serviceContainer->setConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME, $connection);

        self::assertFalse(TheliaKernel::isInstalled());
    }

    private function installedProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(TheliaKernel::class, 'installed');
    }
}
