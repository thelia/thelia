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

namespace Thelia\Install\Standalone;

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
 * one that succeeded because a listener broke afterwards.
 *
 * bin/install runs every command of its post-install phase through run(); thelia:install
 * runs template:set through it, its other steps being processes of their own.
 *
 * @internal
 */
final class CommandExitCodeRecorder
{
    private ?int $exitCode = null;

    /**
     * Run one command of the console and return its exit code. The error a console.terminate
     * listener raises on a container file the command deleted is not the command's: the exit
     * code the command returned is kept. The same error raised before the command returned is
     * a command that did not finish: failure. Any other error is thrown; bin/install counts
     * it as a failed step, thelia:install lets it stop the install.
     *
     * @param EventDispatcherInterface $dispatcher the dispatcher the console dispatches its events on
     */
    public function run(Application $application, InputInterface $input, OutputInterface $output, EventDispatcherInterface $dispatcher): int
    {
        $this->exitCode = null;
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event): void {
            $this->exitCode = $event->getExitCode();
        }, \PHP_INT_MAX);
        $application->setAutoExit(false);

        try {
            return $application->run($input, $output);
        } catch (\Error $error) {
            if (!str_contains($error->getMessage(), 'Failed opening required')) {
                throw $error;
            }

            return $this->exitCode ?? Command::FAILURE;
        }
    }
}
