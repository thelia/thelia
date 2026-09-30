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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Install\Standalone\CommandExitCodeRecorder;

/**
 * The installers read a failed post-install command as a success when a console.terminate
 * listener dies on the cache the command cleared: the recorder keeps the exit code the
 * command returned before any listener can break.
 */
final class CommandExitCodeRecorderTest extends TestCase
{
    public function testTheExitCodeIsKeptWhenALaterTerminateListenerDies(): void
    {
        $dispatcher = new EventDispatcher();
        $recorder = new CommandExitCodeRecorder();
        // Registered first, at the default priority: the recorder still runs before it.
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function (): void {
            throw new \Error('Failed opening required \'var/cache/dev/ContainerAbc/getSomeService.php\'');
        });
        $recorder->listenOn($dispatcher);

        try {
            $dispatcher->dispatch(new ConsoleTerminateEvent(new Command('template:set'), new ArrayInput([]), new NullOutput(), Command::FAILURE), ConsoleEvents::TERMINATE);
            self::fail('The broken listener is expected to throw.');
        } catch (\Error) {
        }

        self::assertSame(Command::FAILURE, $recorder->exitCode());
    }

    public function testNoExitCodeIsKnownBeforeTheCommandTerminates(): void
    {
        $recorder = new CommandExitCodeRecorder();
        $recorder->listenOn(new EventDispatcher());

        self::assertNull($recorder->exitCode());
    }
}
