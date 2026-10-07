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

namespace Thelia\Messenger\Monitoring;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface as MailerTransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Exception\UserFacingFailure;
use Thelia\Log\Tlog;
use Thelia\Mailer\TransportCredentials;
use Thelia\Messenger\Event\FailedJobRemovedEvent;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Messenger\Message\DescribedJob;
use Thelia\Messenger\Message\ReplayableJob;
use Thelia\Messenger\Message\UndecodableJob;
use Thelia\Messenger\Transport\ConfiguredQueues;

/**
 * What the back office shows of the background jobs: how many wait, which failed
 * and why, and the two gestures on a failed one, replaying and deleting it.
 *
 * A failed job keeps what it was dispatched with: the description of an e-mail names
 * its recipients, and the reason of a failure may quote personal data. The screen
 * reading this answers to a resource of its own.
 */
final readonly class BackgroundJobsMonitor
{
    public const DEFAULT_TRANSPORT = 'async';

    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private TransportInterface $jobTransport,
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failureTransport,
        private MessageBusInterface $bus,
        #[Autowire(service: 'messenger.transport.async_heavy')]
        private ?TransportInterface $heavyTransport = null,
        private ?ConfiguredQueues $queues = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * False when MESSENGER_TRANSPORT_DSN names no queue and every job runs at once.
     */
    public function hasQueue(): bool
    {
        return !$this->jobTransport instanceof SyncTransport;
    }

    /**
     * The jobs waiting for a worker, or null when the queue cannot count them.
     */
    public function pendingCount(): ?int
    {
        if (!$this->jobTransport instanceof MessageCountAwareInterface) {
            return null;
        }

        $count = $this->jobTransport->getMessageCount();

        // The heavy jobs wait on a queue of their own, unless it is the same one.
        if ($this->heavyTransport instanceof MessageCountAwareInterface
            && $this->heavyTransport !== $this->jobTransport
            && true !== $this->queues?->heavyJobsShareTheJobQueue()
        ) {
            $count += $this->heavyTransport->getMessageCount();
        }

        return $count;
    }

    public function failedCount(): int
    {
        return $this->failureTransport instanceof MessageCountAwareInterface ? $this->failureTransport->getMessageCount() : 0;
    }

    /**
     * The last jobs set aside, the newest first.
     *
     * @return list<FailedJob>
     */
    public function failedJobs(int $limit = 100): array
    {
        $queue = $this->queues?->failureQueueInTheShopDatabase();

        if (null !== $queue) {
            // Read in SQL: the transport lists the oldest first, and past the limit the
            // newest failures, the ones worth looking at, would never show.
            return array_values(array_filter(array_map($this->find(...), $queue->newestIds($limit))));
        }

        $jobs = [];

        foreach ($this->failureTransport()->all($limit) as $envelope) {
            $jobs[] = self::failedJob($envelope);
        }

        usort($jobs, static fn (FailedJob $a, FailedJob $b): int => ($b->failedAt?->getTimestamp() ?? 0) <=> ($a->failedAt?->getTimestamp() ?? 0));

        return $jobs;
    }

    public function find(string $id): ?FailedJob
    {
        $envelope = $this->failureTransport()->find($id);

        return null === $envelope ? null : self::failedJob($envelope);
    }

    /**
     * Puts a failed job back on the transport it failed on, with a fresh count of
     * attempts. Without a queue it runs at once: when it fails again, the exception
     * reaches the caller and the job is set aside again, as it was.
     *
     * @return bool false when no failed job has this id
     */
    public function retry(string $id): bool
    {
        $envelope = $this->failureTransport()->find($id);

        // A job the shop cannot read fails the same way when replayed: it can only be
        // deleted, whatever was posted.
        if (null === $envelope || $envelope->getMessage() instanceof UndecodableJob) {
            return false;
        }

        $transport = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName() ?? self::DEFAULT_TRANSPORT;

        // Taken out first, so a second click on the same job finds nothing to replay
        // instead of sending it twice; put back as it was when the replay fails. In
        // the shop database the row is deleted on a condition: of two replays that
        // read the job at once, only the one that deleted it sends it again.
        $queue = $this->queues?->failureQueueInTheShopDatabase();

        if (null === $queue) {
            $this->failureTransport->reject($envelope);
        } elseif (!$queue->take($id)) {
            return false;
        }

        try {
            $message = $envelope->getMessage();
            $this->bus->dispatch(new Envelope($message instanceof ReplayableJob ? $message->forReplay() : $message, [new TransportNamesStamp([$transport])]));
        } catch (\Throwable $exception) {
            try {
                $this->failureTransport->send($envelope->withoutAll(TransportMessageIdStamp::class));
            } catch (\Throwable $putBackFailure) {
                // Neither replayed nor set aside again: the log says which job it was, by
                // its id and class only. Its description and its content may name a
                // customer, and the log is kept far longer than the failed jobs.
                Tlog::getInstance()->addCritical(\sprintf('The failed job %s (%s) could be neither replayed (%s) nor set aside again (%s), it is lost from the queues.', $id, $envelope->getMessage()::class, JobFailureMessage::forLog($exception), JobFailureMessage::forLog($putBackFailure)));

                throw new \RuntimeException(\sprintf('The failed job %s could be neither replayed nor set aside again: it is lost from the queues.', $id), 0, $exception);
            }

            throw $exception;
        }

        return true;
    }

    /**
     * @return bool false when no failed job has this id
     */
    public function remove(string $id): bool
    {
        $envelope = $this->failureTransport()->find($id);

        if (null === $envelope) {
            return false;
        }

        $this->failureTransport->reject($envelope);

        // Never replayed now: what was kept for the replay can go.
        $this->dispatcher?->dispatch(new FailedJobRemovedEvent($envelope->getMessage()));

        return true;
    }

    private function failureTransport(): ListableReceiverInterface
    {
        if (!$this->failureTransport instanceof ListableReceiverInterface) {
            throw new \LogicException(\sprintf('The failure transport (%s) cannot list its jobs.', $this->failureTransport::class));
        }

        return $this->failureTransport;
    }

    private static function failedJob(Envelope $envelope): FailedJob
    {
        $message = $envelope->getMessage();
        $error = $envelope->last(ErrorDetailsStamp::class);

        return new FailedJob(
            (string) $envelope->last(TransportMessageIdStamp::class)?->getId(),
            $message instanceof UndecodableJob ? $message->originalType : $message::class,
            self::describe($message),
            $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt(),
            \count($envelope->all(RedeliveryStamp::class)),
            self::reasonOf($error),
            $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName(),
            // Replayed, a job the shop cannot read fails the same way.
            !$message instanceof UndecodableJob,
        );
    }

    /**
     * Why the job failed, as the administrator may read it: a reason written for them
     * (a job of the shop, an unreadable one), or what the mail server answered, its
     * credentials hidden. Anything else, a database error quoting a customer among
     * them, reads as a server error.
     */
    private static function reasonOf(?ErrorDetailsStamp $error): string
    {
        if (null === $error) {
            return '';
        }

        $class = $error->getExceptionClass();

        // Bounded as any other reason: an unreadable job quotes what the queue held.
        return match (true) {
            is_a($class, UserFacingFailure::class, true) => mb_substr($error->getExceptionMessage(), 0, JobFailureMessage::MAX_LENGTH),
            is_a($class, MailerTransportException::class, true) => mb_substr(TransportCredentials::hide($error->getExceptionMessage()), 0, JobFailureMessage::MAX_LENGTH),
            default => JobFailureMessage::SERVER_ERROR,
        };
    }

    private static function describe(object $message): string
    {
        if ($message instanceof DescribedJob) {
            return $message->describe();
        }

        if ($message instanceof SendEmailMessage && $message->getMessage() instanceof Email) {
            $email = $message->getMessage();

            return \sprintf('%s → %s', (string) $email->getSubject(), implode(', ', array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo())));
        }

        $parts = explode('\\', $message::class);

        return end($parts);
    }
}
