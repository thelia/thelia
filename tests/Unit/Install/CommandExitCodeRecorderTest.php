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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Install\Standalone\CommandExitCodeRecorder;

/**
 * The installers read a failed post-install command as a success when a console.terminate
 * listener dies on the cache the command cleared: the recorder keeps the exit code the
 * command returned before any listener can break, on a real console application.
 */
final class CommandExitCodeRecorderTest extends TestCase
{
    public function testAFailedCommandStaysFailedWhenATerminateListenerDiesOnTheClearedCache(): void
    {
        $dispatcher = $this->dispatcherWhoseTerminateListenerThrows('Failed opening required \'var/cache/dev/ContainerAbc/getSomeService.php\'');

        self::assertSame(Command::FAILURE, $this->runCommand(static fn (): int => Command::FAILURE, $dispatcher));
    }

    public function testASuccessfulCommandStaysSuccessfulWhenATerminateListenerDiesOnTheClearedCache(): void
    {
        $dispatcher = $this->dispatcherWhoseTerminateListenerThrows('Failed opening required \'var/cache/dev/ContainerAbc/getSomeService.php\'');

        self::assertSame(Command::SUCCESS, $this->runCommand(static fn (): int => Command::SUCCESS, $dispatcher));
    }

    public function testACommandThatTerminatesNormallyReturnsItsExitCode(): void
    {
        self::assertSame(Command::FAILURE, $this->runCommand(static fn (): int => Command::FAILURE, new EventDispatcher()));
    }

    /**
     * The command itself dies on a missing container file, before it returns anything:
     * it did not finish, whatever it did before.
     */
    public function testACommandThatDiesBeforeReturningIsAFailure(): void
    {
        $exitCode = $this->runCommand(static function (): never {
            throw new \Error('Failed opening required \'var/cache/dev/ContainerAbc/getSomeService.php\'');
        }, new EventDispatcher());

        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function testAnErrorThatIsNotTheClearedCacheIsThrown(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method Foo::bar()');

        $this->runCommand(static fn (): int => Command::SUCCESS, $this->dispatcherWhoseTerminateListenerThrows('Call to undefined method Foo::bar()'));
    }

    private function dispatcherWhoseTerminateListenerThrows(string $message): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        // Registered before the recorder's listener, at the default priority: the recorder
        // still reads the exit code first.
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function () use ($message): never {
            throw new \Error($message);
        });

        return $dispatcher;
    }

    private function runCommand(callable $code, EventDispatcher $dispatcher): int
    {
        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->addCommand((new Command('sample:run'))->setCode($code));

        return (new CommandExitCodeRecorder())->run($application, new ArrayInput(['command' => 'sample:run']), new NullOutput(), $dispatcher);
    }
}
