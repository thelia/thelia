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
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Thelia\Install\CommandExitCodeRecorder;

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

    /**
     * The listeners of a compiled container are lazy: `[closure, method]`, the closure loading
     * the service from the container cache. Depending on the console, the listener is resolved
     * when console.terminate is dispatched, after the command cleared the cache (the closure
     * dies), or before the command runs, as symfony/console 7.4.21 does when pcntl is loaded
     * (the listener then dies on a service it loads when it is called). Either way it dies
     * after the command returned, and anything that resolves it again (removing a listener
     * does) dies again: the exit code the command returned is kept all the same.
     */
    public function testTheExitCodeIsKeptWhenALazyTerminateListenerDiesOnTheClearedCache(): void
    {
        foreach ([Command::FAILURE, Command::SUCCESS] as $returned) {
            $cacheCleared = false;
            $loadFromCache = static function (string $file) use (&$cacheCleared): void {
                if ($cacheCleared) {
                    throw new \Error(\sprintf('Failed opening required \'var/cache/dev/ContainerAbc/%s\'', $file));
                }
            };
            $dispatcher = new EventDispatcher();
            $dispatcher->addListener(ConsoleEvents::TERMINATE, [static function () use ($loadFromCache): object {
                $loadFromCache('getSomeListenerService.php');

                return new class($loadFromCache) {
                    public function __construct(private readonly \Closure $loadFromCache)
                    {
                    }

                    public function onConsoleTerminate(): void
                    {
                        ($this->loadFromCache)('getSomeDependencyService.php');
                    }
                };
            }, 'onConsoleTerminate']);

            $command = static function () use (&$cacheCleared, $returned): int {
                $cacheCleared = true;

                return $returned;
            };

            self::assertSame($returned, $this->runCommand($command, $dispatcher));
        }
    }

    /**
     * The traceable dispatcher of a debug kernel resolves every listener before calling the
     * first: a lazy listener that dies when it is resolved dies before the exit code is
     * recorded (before the command runs, with symfony/console 7.4.21 and pcntl), and a
     * command whose result cannot be read is a failure. The installers boot their kernels without debug.
     */
    public function testATraceableDispatcherThatDiesBeforeTheRecorderReadsAFailure(): void
    {
        $inner = new EventDispatcher();
        $inner->addListener(ConsoleEvents::TERMINATE, [static function (): never {
            throw new \Error('Failed opening required \'var/cache/dev/ContainerAbc/getSomeListenerService.php\'');
        }, 'onConsoleTerminate']);

        self::assertSame(Command::FAILURE, $this->runCommand(static fn (): int => Command::SUCCESS, new TraceableEventDispatcher($inner, new Stopwatch())));
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

    private function runCommand(callable $code, EventDispatcherInterface $dispatcher): int
    {
        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->addCommand((new Command('sample:run'))->setCode($code));

        return (new CommandExitCodeRecorder())->run($application, new ArrayInput(['command' => 'sample:run']), new NullOutput(), $dispatcher);
    }
}
