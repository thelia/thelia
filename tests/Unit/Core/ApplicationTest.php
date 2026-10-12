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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Application;
use Thelia\Core\TheliaKernel;

/**
 * Work the application defers to the end of the process — a cache clear queued
 * by a module activation, for instance — only runs if the console emits
 * console.terminate, which Symfony does only when a dispatcher is attached.
 * A shop that is not installed yet is only offered the install command.
 */
final class ApplicationTest extends TestCase
{
    private ServiceContainerInterface $propelServiceContainer;

    private ?bool $installed;

    /** @var array<string, array{server: mixed, env: mixed, process: string|false}> */
    private array $databaseEnvironment = [];

    protected function setUp(): void
    {
        $this->propelServiceContainer = Propel::getServiceContainer();
        Propel::setServiceContainer(new StandardServiceContainer());

        // Whether the shop is installed is cached in a private static of the
        // kernel: it is saved and cleared so that no database and no other
        // test decides which commands this one sees, and restored after.
        $this->installed = $this->installedProperty()->getValue();
        $this->installedProperty()->setValue(null, null);

        foreach (['DATABASE_HOST', 'DATABASE_PORT'] as $name) {
            $this->databaseEnvironment[$name] = [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'process' => getenv($name),
            ];
        }
        $this->setEnvironment('DATABASE_HOST', '');
    }

    protected function tearDown(): void
    {
        foreach ($this->databaseEnvironment as $name => $saved) {
            unset($_SERVER[$name], $_ENV[$name]);

            if (null !== $saved['server']) {
                $_SERVER[$name] = $saved['server'];
            }

            if (null !== $saved['env']) {
                $_ENV[$name] = $saved['env'];
            }

            putenv(false === $saved['process'] ? $name : $name.'='.$saved['process']);
        }

        $this->installedProperty()->setValue(null, $this->installed);
        Propel::setServiceContainer($this->propelServiceContainer);
    }

    public function testTheConsoleEmitsTheTerminateEvent(): void
    {
        $terminated = 0;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ConsoleEvents::TERMINATE,
            static function () use (&$terminated): void { ++$terminated; },
        );

        $application = new Application($this->kernelWith($dispatcher));
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $exitCode = $application->run(new ArrayInput(['command' => 'list']), new NullOutput());

        self::assertSame(0, $exitCode);
        self::assertSame(1, $terminated);
    }

    public function testBuildingTheConsoleBootsTheKernel(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('dev');
        $kernel->expects(self::once())->method('boot');

        new Application($kernel);
    }

    public function testTheEnvironmentOptionDefaultsToTheEnvironmentOfTheKernel(): void
    {
        $option = (new Application($this->kernelWith(new EventDispatcher())))->getDefinition()->getOption('env');

        self::assertSame('e', $option->getShortcut());
        self::assertSame('test', $option->getDefault());
    }

    public function testTheConsoleAcceptsTheNoDebugOption(): void
    {
        $definition = (new Application($this->kernelWith(new EventDispatcher())))->getDefinition();

        self::assertTrue($definition->hasOption('no-debug'));
        self::assertFalse($definition->getOption('no-debug')->acceptValue());
    }

    public function testAShopNotInstalledYetIsOnlyOfferedTheInstallCommand(): void
    {
        $application = $this->runListOn($this->kernelWith(new EventDispatcher(), ['shop.command.probe' => $this->probeCommand()]));

        self::assertTrue($application->has('thelia:install'));
        self::assertFalse($application->has('shop:probe'));
    }

    public function testAnInstalledShopIsOfferedTheCommandsOfItsContainer(): void
    {
        $this->installShop();

        $application = $this->runListOn($this->kernelWith(new EventDispatcher(), ['shop.command.probe' => $this->probeCommand()]));

        self::assertTrue($application->has('shop:probe'));
        self::assertFalse($application->has('thelia:install'));
    }

    private function runListOn(KernelInterface $kernel): Application
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'list']), new NullOutput()));

        return $application;
    }

    private function probeCommand(): Command
    {
        return new class('shop:probe') extends Command {
        };
    }

    /**
     * The shop's config table answers through the Propel connection; the database
     * port is one nothing listens on, so no real server can answer instead, not
     * even the MySQL a CI runner keeps on 127.0.0.1:3306.
     */
    private function installShop(): void
    {
        $this->setEnvironment('DATABASE_HOST', '127.0.0.1');
        $this->setEnvironment('DATABASE_PORT', '1');

        $result = $this->createMock(PDODataFetcher::class);
        $result->method('fetch')->willReturn(['id' => 1]);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')->willReturn($result);

        $serviceContainer = Propel::getServiceContainer();
        \assert($serviceContainer instanceof StandardServiceContainer);
        $serviceContainer->setAdapterClass(DatabaseConfiguration::THELIA_CONNECTION_NAME, 'mysql');
        $serviceContainer->setConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME, $connection);
    }

    private function setEnvironment(string $name, string $value): void
    {
        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }

    private function installedProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(TheliaKernel::class, 'installed');
    }

    /**
     * @param array<string, Command> $commands the commands the container declares, by service id
     */
    private function kernelWith(EventDispatcher $dispatcher, array $commands = []): KernelInterface
    {
        $container = new Container();
        $container->setParameter('command.definition', array_keys($commands));
        $container->set('event_dispatcher', $dispatcher);

        foreach ($commands as $id => $command) {
            $container->set($id, $command);
        }

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getEnvironment')->willReturn('test');

        return $kernel;
    }
}
