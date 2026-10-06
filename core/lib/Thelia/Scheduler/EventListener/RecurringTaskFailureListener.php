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

namespace Thelia\Scheduler\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\RunCommandFailedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Scheduler\RecurringTaskFailures;
use Thelia\Scheduler\TheliaSchedule;

/**
 * Keeps the last failure of each task of the thelia schedule for the back office,
 * and forgets it once the task runs through.
 */
final readonly class RecurringTaskFailureListener
{
    public const RECEIVER = 'scheduler_'.TheliaSchedule::NAME;

    public function __construct(
        private RecurringTaskFailures $failures,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener]
    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if (self::RECEIVER !== $event->getReceiverName()) {
            return;
        }

        $exception = $event->getThrowable();
        $cause = $exception instanceof HandlerFailedException ? ($exception->getPrevious() ?? $exception) : $exception;
        $task = RecurringTaskFailures::taskOf($event->getEnvelope()->getMessage());

        // Named by its class and place unless its words were meant for the screen: the
        // log is kept longer than the failure, and a database error quotes values.
        $this->logger->error(\sprintf('The recurring task %s failed: %s', $task, JobFailureMessage::forLog($cause)));
        $this->failures->record($task, self::reasonOf($cause));
    }

    #[AsEventListener]
    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        if (self::RECEIVER === $event->getReceiverName()) {
            $this->failures->forget(RecurringTaskFailures::taskOf($event->getEnvelope()->getMessage()));
        }
    }

    /**
     * A command that exits with an error says only that, which is safe to show; one
     * that throws carries the exception, read as any job failure.
     */
    private static function reasonOf(\Throwable $cause): string
    {
        if ($cause instanceof RunCommandFailedException && null === $cause->getPrevious()) {
            return $cause->getMessage();
        }

        return JobFailureMessage::forAdministrator($cause);
    }
}
