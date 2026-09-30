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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The installers run each post-install command in a kernel whose cache the command may
 * clear: a console.terminate listener can then die loading a service whose file is gone,
 * after the command returned but before the console hands its exit code back. Listening
 * first on console.terminate keeps that exit code, so a failed command is never read as
 * one that succeeded because a listener broke afterwards.
 */
final class CommandExitCodeRecorder
{
    private ?int $exitCode = null;

    public function listenOn(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event): void {
            $this->exitCode = $event->getExitCode();
        }, \PHP_INT_MAX);
    }

    /**
     * The exit code of the command, or null when the console never reached console.terminate.
     */
    public function exitCode(): ?int
    {
        return $this->exitCode;
    }
}
