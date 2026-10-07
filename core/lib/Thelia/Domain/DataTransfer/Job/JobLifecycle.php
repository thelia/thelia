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

namespace Thelia\Domain\DataTransfer\Job;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Messenger\JobSetAsideException;
use Thelia\Messenger\Transport\ConfiguredQueues;

/**
 * The steps every export or import job goes through, written once for both.
 *
 * A job is queued, taken by one worker, then done or failed. A job found running is
 * either still worked on (its worker gives signs of life) or left behind by a worker
 * that died: in both cases the message is not dropped, it is looked at again later,
 * until the job is over or has been silent long enough to be taken again
 * ({@see JobClaim}). A message delivered again while its worker is alive would
 * otherwise be acknowledged and the job left running forever.
 *
 * What a failure says goes through {@see JobFailureMessage}: the row, the failure
 * transport and the log never carry the text of a database or PHP error.
 */
final readonly class JobLifecycle
{
    /** How long a job found running waits before it is looked at again. */
    public const POSTPONE_DELAY_SECONDS = 600;

    /** Twelve hours of looking again, then the message is set aside. */
    public const MAX_POSTPONEMENTS = 72;

    public const NOT_QUEUED = 'The job could not be queued. The details are in the server log.';

    public function __construct(
        private JobClaim $jobClaim,
        private MessageBusInterface $bus,
        private ConfiguredQueues $queues,
    ) {
    }

    /**
     * Records the job's message on the queue, or runs it at once without one.
     *
     * A queue that refuses the message leaves a row nobody will ever take: the row is
     * failed, and the caller learns why.
     */
    public function dispatch(DataTransferJob $job, DataTransferJobMessage $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (HandlerFailedException $exception) {
            // Run at once, without a queue. The handler wrote why on the row, unless it
            // failed before taking the job: then the row would wait for nobody.
            $job->refresh();

            if (JobStatus::QUEUED === $job->getJobStatus()) {
                Tlog::getInstance()->addError(\sprintf('%s %d failed before it started: %s', $job::class, $job->getId(), JobFailureMessage::forLog($exception)));
                $job->markFailed(JobFailureMessage::forAdministrator($exception));
            }

            return;
        } catch (\Throwable $exception) {
            Tlog::getInstance()->addError(\sprintf('%s %d could not be queued: %s', $job::class, $job->getId(), JobFailureMessage::forLog($exception)));
            $job->markFailed(self::NOT_QUEUED);

            throw $exception;
        }

        $job->refresh();
    }

    /**
     * Takes the job for this run, or, when another run holds it, sends the message
     * again to look at it later (set aside after twelve hours).
     */
    public function claimOrPostpone(DataTransferJob $job, DataTransferJobMessage $message): ClaimOutcome
    {
        // Only the message first sent, or replayed by an administrator, may restart a
        // failed job: one looking again leaves it to the administrator.
        $claimed = $this->jobClaim->claim($job->tableName(), (int) $job->getId(), 0 === $message->postponements());
        $job->refresh();

        if ($claimed) {
            return ClaimOutcome::Owned;
        }

        if (JobStatus::RUNNING !== $job->getJobStatus() || $this->queues->heavyJobsRunInline()) {
            // Over, or without a queue: looking again would run at once, in this very
            // call, over and over, and the run that holds the job finishes it.
            return ClaimOutcome::Finished;
        }

        if ($message->postponements() >= self::MAX_POSTPONEMENTS) {
            // Set aside rather than dropped: the failed jobs show it, and replaying it
            // takes the job over once its worker has gone quiet.
            throw new JobSetAsideException(\sprintf('%s %d is still running after %d checks: it is set aside with the failed jobs.', $job::class, $job->getId(), $message->postponements()));
        }

        $this->bus->dispatch($message->postponed(), [new DelayStamp(self::POSTPONE_DELAY_SECONDS * 1000)]);

        return ClaimOutcome::Postponed;
    }

    /**
     * Sets a message aside without touching its row: the row may be unreadable, or
     * held by another run. What the failure transport keeps says no more than
     * JobFailureMessage allows; the log names the exception.
     */
    public function reject(string $job, \Throwable $exception): never
    {
        Tlog::getInstance()->addError(\sprintf('%s could not be taken: %s', $job, JobFailureMessage::forLog($exception)));

        throw new JobSetAsideException(\sprintf('%s could not be taken: %s', $job, JobFailureMessage::forAdministrator($exception)));
    }

    /**
     * False without a queue: a job that fails is not kept anywhere it could be replayed
     * from, so what it holds (an uploaded file) is not kept for it either.
     */
    public function keepsFailedJobs(): bool
    {
        return !$this->queues->heavyJobsRunInline();
    }

    /**
     * Records why the job failed and sends it to the failure transport, without
     * retries: running it again unchanged fails the same way.
     */
    public function fail(DataTransferJob $job, \Throwable $exception): never
    {
        Tlog::getInstance()->addError(\sprintf('%s %d failed: %s', $job::class, $job->getId(), JobFailureMessage::forLog($exception)));

        $reason = JobFailureMessage::forAdministrator($exception);

        try {
            // Read again first: what the run wrote in memory went with its transaction.
            $job->refresh();
            $job->markFailed($reason);
        } catch (\Throwable $notRecorded) {
            // The job is set aside all the same: the failure transport still lists it.
            Tlog::getInstance()->addError(\sprintf('%s %d could not be marked failed: %s', $job::class, $job->getId(), JobFailureMessage::forLog($notRecorded)));
        }

        // The failure transport keeps this exception and the back office lists it.
        // Symfony stores the whole chain of an exception it sets aside, so the cause
        // is not chained: its text may quote a customer, the log names it.
        throw new JobSetAsideException(\sprintf('%s %d failed: %s', $job::class, $job->getId(), $reason));
    }
}
