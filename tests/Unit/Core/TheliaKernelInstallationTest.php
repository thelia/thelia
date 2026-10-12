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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\PDODataFetcher;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\TheliaKernel;

/**
 * A shop is installed once its config table holds a row, asked on the Propel
 * connection when there is one. A positive answer is kept for the rest of the
 * process; a shop without a database host is never installed.
 */
final class TheliaKernelInstallationTest extends TestCase
{
    private const ENVIRONMENT_VARIABLES = ['DATABASE_HOST', 'DATABASE_PORT'];

    private ServiceContainerInterface $propelServiceContainer;

    private ?bool $installed;

    /** @var array<string, array{server: mixed, env: mixed, process: string|false}> */
    private array $environment = [];

    protected function setUp(): void
    {
        $this->propelServiceContainer = Propel::getServiceContainer();
        Propel::setServiceContainer(new StandardServiceContainer());

        // The answer is cached in a private static: it is saved and cleared so
        // that no other test decides what this one sees, and restored after.
        $this->installed = $this->installedProperty()->getValue();
        $this->installedProperty()->setValue(null, null);

        foreach (self::ENVIRONMENT_VARIABLES as $name) {
            $this->environment[$name] = [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'process' => getenv($name),
            ];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $saved) {
            $this->restore($_SERVER, $name, $saved['server']);
            $this->restore($_ENV, $name, $saved['env']);
            putenv(false === $saved['process'] ? $name : $name.'='.$saved['process']);
        }

        $this->installedProperty()->setValue(null, $this->installed);
        Propel::setServiceContainer($this->propelServiceContainer);
    }

    public function testAShopWithoutADatabaseHostIsNotInstalled(): void
    {
        $this->setEnvironment('DATABASE_HOST', '');

        self::assertFalse(TheliaKernel::isInstalled());
    }

    public function testAShopWhoseConfigTableHoldsARowIsInstalled(): void
    {
        $this->useUnreachableDatabaseServer();
        $this->connectPropelTo($this->connectionAnswering(['id' => 1]));

        self::assertTrue(TheliaKernel::isInstalled());
    }

    public function testAShopWhoseConfigTableIsEmptyIsNotInstalledYet(): void
    {
        $this->useUnreachableDatabaseServer();
        $this->connectPropelTo($this->connectionAnswering(false));

        self::assertFalse(TheliaKernel::isInstalled());
    }

    public function testAnInstalledShopIsNotAskedTwice(): void
    {
        $this->useUnreachableDatabaseServer();

        $connection = $this->connectionAnswering(['id' => 1]);
        $connection->expects(self::once())->method('query')->with('SELECT id FROM `config`');
        $this->connectPropelTo($connection);

        self::assertTrue(TheliaKernel::isInstalled());
        self::assertTrue(TheliaKernel::isInstalled());
    }

    public function testAVariableSetOnTheServerIsReadFirst(): void
    {
        $this->setEnvironment('DATABASE_PORT', '3309');
        $_ENV['DATABASE_PORT'] = '3308';
        $_SERVER['DATABASE_PORT'] = '3307';

        self::assertSame('3307', TheliaKernel::resolveEnv('DATABASE_PORT'));
    }

    public function testAVariableMissingFromTheServerIsReadFromTheEnvironment(): void
    {
        $this->setEnvironment('DATABASE_PORT', null);
        $_ENV['DATABASE_PORT'] = '3308';

        self::assertSame('3308', TheliaKernel::resolveEnv('DATABASE_PORT'));
    }

    public function testANumericServerValueComesBackAsAString(): void
    {
        $this->setEnvironment('DATABASE_PORT', null);
        $_SERVER['DATABASE_PORT'] = 3306;

        self::assertSame('3306', TheliaKernel::resolveEnv('DATABASE_PORT'));
    }

    public function testANumericEnvironmentValueComesBackAsAString(): void
    {
        $this->setEnvironment('DATABASE_PORT', null);
        $_ENV['DATABASE_PORT'] = 3306;

        self::assertSame('3306', TheliaKernel::resolveEnv('DATABASE_PORT'));
    }

    public function testAVariableOnlyInTheProcessEnvironmentIsRead(): void
    {
        $this->setEnvironment('DATABASE_PORT', null);
        putenv('DATABASE_PORT=3310');

        self::assertSame('3310', TheliaKernel::resolveEnv('DATABASE_PORT', '3306'));
    }

    public function testAnEmptyVariableFallsBackToTheDefault(): void
    {
        $this->setEnvironment('DATABASE_PORT', '');

        self::assertSame('3306', TheliaKernel::resolveEnv('DATABASE_PORT', '3306'));
    }

    /**
     * Only the Propel connection may answer: were the kernel to open a
     * connection of its own, nothing listens on this port and it would say
     * "not installed".
     */
    private function useUnreachableDatabaseServer(): void
    {
        $this->setEnvironment('DATABASE_HOST', '127.0.0.1');
        $this->setEnvironment('DATABASE_PORT', '1');
    }

    private function connectPropelTo(ConnectionInterface $connection): void
    {
        $serviceContainer = Propel::getServiceContainer();
        \assert($serviceContainer instanceof StandardServiceContainer);

        $serviceContainer->setAdapterClass(DatabaseConfiguration::THELIA_CONNECTION_NAME, 'mysql');
        $serviceContainer->setConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME, $connection);
    }

    /**
     * @param array<string, int>|false $row what the first fetch of the config table returns
     */
    private function connectionAnswering(array|false $row): ConnectionInterface&MockObject
    {
        $result = $this->createMock(PDODataFetcher::class);
        $result->method('fetch')->willReturn($row);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')->willReturn($result);

        return $connection;
    }

    /**
     * Writes the variable in every source TheliaKernel::resolveEnv() reads;
     * null removes it from all of them.
     */
    private function setEnvironment(string $name, ?string $value): void
    {
        if (null === $value) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function restore(array &$source, string $name, mixed $value): void
    {
        if (null === $value) {
            unset($source[$name]);

            return;
        }

        $source[$name] = $value;
    }

    private function installedProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(TheliaKernel::class, 'installed');
    }
}
