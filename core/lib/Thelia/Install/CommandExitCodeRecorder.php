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

namespace Thelia\Install;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The installers run each post-install command in a kernel whose cache the command may
 * clear: a console.terminate listener can then die loading a service whose file is gone,
 * after the command returned but before the console hands its exit code back. Listening
 * first on console.terminate keeps that exit code, so a failed command is never read as
 * one that succeeded because a listener broke afterwards. That holds for the plain event
 * dispatcher of a kernel booted without debug, which resolves a lazy listener when it
 * calls it: the traceable dispatcher of a debug kernel resolves them all first, and the
 * command is then read as failed. Both installers boot their kernels without debug.
 *
 * bin/install runs every command of its post-install phase through run(); thelia:install
 * runs template:set through it, its other steps being processes of their own.
 *
 * @internal
 */
final readonly class CommandExitCodeRecorder
{
    /**
     * Run one command of the console and return its exit code. The error a console.terminate
     * listener raises on a container file the command deleted is not the command's: the exit
     * code the command returned is kept. The same error raised by the command itself is a
     * failure: the console dispatches console.terminate with a non-zero exit code (the code of
     * the error, 1 by default) before it throws the error again, and that code is recorded.
     * The fallback to failure only serves when no exit code reached the recorder: a dispatcher
     * that dies before it calls the recorder's listener. Any other error is thrown; bin/install
     * counts it as a failed step, thelia:install lets it stop the install.
     *
     * @param EventDispatcherInterface $dispatcher the dispatcher the console dispatches its events on
     */
    public function run(Application $application, InputInterface $input, OutputInterface $output, EventDispatcherInterface $dispatcher): int
    {
        $exitCode = null;
        // The listener is not removed: removing one resolves every lazy listener of the
        // event again, the one that died on the deleted container file included, and each
        // caller discards the kernel, and its dispatcher, once the command is over.
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function (ConsoleTerminateEvent $event) use (&$exitCode): void {
            $exitCode = $event->getExitCode();
        }, \PHP_INT_MAX);
        $application->setAutoExit(false);

        try {
            return $application->run($input, $output);
        } catch (\Error $error) {
            if (!str_contains($error->getMessage(), 'Failed opening required')) {
                throw $error;
            }

            return $exitCode ?? Command::FAILURE;
        }
    }
}
