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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\ExportJob;
use Thelia\Model\ImportJob;

/**
 * The steps every export or import job goes through, written once for both.
 *
 * A job is queued, taken by one worker, then done or failed. A job found running is
 * either still worked on (its worker gives signs of life) or left behind by a worker
 * that died: in both cases the message is not dropped, it is looked at again later,
 * until the job is over or has been silent long enough to be taken again
 * ({@see JobClaim}). A message delivered again while its worker is alive would
 * otherwise be acknowledged and the job left running forever.
 */
final readonly class JobLifecycle
{
    /** How long a job found running waits before it is looked at again. */
    public const POSTPONE_DELAY_SECONDS = 600;

    /** Twelve hours of looking again, then the message gives up and says so. */
    public const MAX_POSTPONEMENTS = 72;

    public function __construct(
        private JobClaim $jobClaim,
        private MessageBusInterface $bus,
        #[Autowire('%env(thelia_heavy_queue:MESSENGER_TRANSPORT_DSN)%')]
        private string $heavyTransportDsn = 'sync://',
    ) {
    }

    /**
     * Records the job's message on the queue, or runs it at once without one.
     *
     * A queue that refuses the message leaves a row nobody will ever take: the row is
     * failed, and the caller learns why.
     */
    public function dispatch(ExportJob|ImportJob $job, DataTransferJobMessage $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (HandlerFailedException) {
            // Run at once, without a queue: the handler has written why on the row.
        } catch (\Throwable $exception) {
            Tlog::getInstance()->addError(\sprintf('%s %d could not be queued: %s', $job::class, $job->getId(), $exception->getMessage()));

            $job->setStatus(JobStatus::FAILED->value)
                ->setError('The job could not be queued. The details are in the server log.')
                ->setFinishedAt(new \DateTime())
                ->save();

            throw $exception;
        }

        $job->reload();
    }

    /**
     * @param 'export_job'|'import_job' $table
     *
     * @return bool true when this run owns the job; false when it is done, or running
     *              elsewhere (then looked at again later)
     */
    public function claim(ExportJob|ImportJob $job, string $table, DataTransferJobMessage $message): bool
    {
        $claimed = $this->jobClaim->claim($table, (int) $job->getId());
        $job->reload();

        if ($claimed || JobStatus::RUNNING !== $job->getJobStatus()) {
            return $claimed;
        }

        if (str_starts_with($this->heavyTransportDsn, 'sync://')) {
            // Without a queue, looking again would run at once, in this very call,
            // over and over: the run that holds the job finishes it.
            return false;
        }

        if ($message->postponements() >= self::MAX_POSTPONEMENTS) {
            Tlog::getInstance()->addWarning(\sprintf('%s %d is still running after %d checks: no more checks will be made.', $job::class, $job->getId(), $message->postponements()));

            return false;
        }

        $this->bus->dispatch($message->postponed(), [new DelayStamp(self::POSTPONE_DELAY_SECONDS * 1000)]);

        return false;
    }

    /**
     * Records why the job failed and sends it to the failure transport, without
     * retries: running it again unchanged fails the same way.
     */
    public function fail(ExportJob|ImportJob $job, \Throwable $exception): never
    {
        Tlog::getInstance()->addError(\sprintf('%s %d failed: %s', $job::class, $job->getId(), $exception->getMessage()));

        $job->setStatus(JobStatus::FAILED->value)
            ->setError(JobFailureMessage::forAdministrator($exception))
            ->setFinishedAt(new \DateTime())
            ->save();

        throw new UnrecoverableMessageHandlingException(\sprintf('%s %d failed: %s', $job::class, $job->getId(), $exception->getMessage()), 0, $exception);
    }
}
