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
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Core\Application;

/**
 * Work the application defers to the end of the process — a cache clear queued
 * by a module activation, for instance — only runs if the console emits
 * console.terminate, which Symfony does only when a dispatcher is attached.
 */
final class ApplicationTest extends TestCase
{
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

    private function kernelWith(EventDispatcher $dispatcher): KernelInterface
    {
        $container = new Container();
        $container->setParameter('command.definition', []);
        $container->set('event_dispatcher', $dispatcher);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getEnvironment')->willReturn('test');

        return $kernel;
    }
}
