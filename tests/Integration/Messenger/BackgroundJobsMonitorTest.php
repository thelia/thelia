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

namespace Thelia\Tests\Integration\Messenger;

use Symfony\Component\Mailer\Exception\TransportException as MailerTransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Messenger\JobSetAsideException;
use Thelia\Messenger\Message\UndecodableJob;
use Thelia\Messenger\Monitoring\BackgroundJobsMonitor;
use Thelia\Messenger\Transport\ConfiguredQueues;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Messenger\Transport\ShopDatabaseTransportFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;
use Thelia\Tests\Support\Messenger\ProbeSerializer;

/**
 * What the back office reads of the queues, and the two gestures it has on a failed
 * job. Played on queue names nothing else uses, emptied afterwards: the transports
 * write through a connection the test transaction does not cover.
 */
final class BackgroundJobsMonitorTest extends IntegrationTestCase
{
    private DoctrineTransport $jobs;

    private DoctrineTransport $failed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobs = $this->transport('test_monitor_jobs');
        $this->failed = $this->transport('test_monitor_failed');
        $this->empty();
    }

    protected function tearDown(): void
    {
        $this->empty();

        parent::tearDown();
    }

    public function testAFailedJobIsListedWithWhatFailedAndWhy(): void
    {
        $this->setAside($this->mail(), 'Connection could not be established with host "smtp.example.com"');

        $jobs = $this->monitor()->failedJobs();

        self::assertCount(1, $jobs);
        self::assertSame(SendEmailMessage::class, $jobs[0]->messageClass);
        self::assertSame('Your order ORD000000000042 → buyer@example.com', $jobs[0]->description);
        self::assertSame('Connection could not be established with host "smtp.example.com"', $jobs[0]->error);
        self::assertSame('async', $jobs[0]->transport);
        self::assertSame(1, $jobs[0]->attempts);
        self::assertNotNull($jobs[0]->failedAt);
        self::assertSame(1, $this->monitor()->failedCount());
    }

    /**
     * Only a reason written for the administrator, or the answer of the mail server,
     * is shown: what any other handler threw may quote a customer.
     */
    public function testAFailureIsShownOnlyWhenItsReasonIsMeantToBe(): void
    {
        $this->setAside(new ProbeMessage('module job'), "SQLSTATE[23000]: Duplicate entry 'buyer@example.com'", \PDOException::class);
        $this->setAside($this->mail(), 'Failed to authenticate on SMTP server with smtp://shop:s3cr3t@mail.example.com');
        $this->setAside(new ProbeMessage('import'), 'Import #3 failed: The following columns are missing: stock', JobSetAsideException::class);

        $reasons = array_map(static fn ($job): string => $job->error, $this->monitor()->failedJobs());

        self::assertContains(JobFailureMessage::SERVER_ERROR, $reasons);
        self::assertContains('Failed to authenticate on SMTP server with smtp://***@mail.example.com', $reasons);
        self::assertContains('Import #3 failed: The following columns are missing: stock', $reasons);
        self::assertStringNotContainsString('buyer@example.com', implode(' ', $reasons));
        self::assertStringNotContainsString('s3cr3t', implode(' ', $reasons));
    }

    public function testTheJobsWaitingForAWorkerAreCounted(): void
    {
        $this->jobs->send(new Envelope(new ProbeMessage('waiting')));
        $this->jobs->send(new Envelope(new ProbeMessage('waiting too')));

        self::assertTrue($this->monitor()->hasQueue());
        self::assertSame(2, $this->monitor()->pendingCount());
    }

    /**
     * The exports and imports wait on a queue of their own: the screen counts both.
     */
    public function testTheHeavyJobsAreCountedWithTheOthers(): void
    {
        $heavy = $this->transport('test_monitor_heavy');

        try {
            $this->jobs->send(new Envelope(new ProbeMessage('a mail')));
            $heavy->send(new Envelope(new ProbeMessage('an export')));
            $heavy->send(new Envelope(new ProbeMessage('an import')));

            $monitor = new BackgroundJobsMonitor($this->jobs, $this->failed, $this->getService(MessageBusInterface::class), $heavy);

            self::assertSame(3, $monitor->pendingCount());
        } finally {
            foreach ($heavy->all() as $envelope) {
                $heavy->reject($envelope);
            }
        }
    }

    /**
     * A queue that cannot be told apart (an AMQP or a Redis one MESSENGER_HEAVY_TRANSPORT_DSN
     * does not split) holds the heavy jobs with the others: each is counted once.
     */
    public function testHeavyJobsSharingTheJobQueueAreCountedOnce(): void
    {
        $sameQueue = $this->transport('test_monitor_jobs');
        $this->jobs->send(new Envelope(new ProbeMessage('an export')));

        $monitor = new BackgroundJobsMonitor($this->jobs, $this->failed, $this->getService(MessageBusInterface::class), $sameQueue, $this->queues(heavyDsn: 'doctrine://default?queue_name=test_monitor_jobs'));

        self::assertSame(1, $monitor->pendingCount());
    }

    /**
     * The newest failures are the ones worth looking at: past the limit, the oldest
     * are left out, never the newest.
     */
    public function testTheNewestFailuresAreListedFirstAndKeptWithinTheLimit(): void
    {
        $this->setAside(new ProbeMessage('oldest'), 'first', JobSetAsideException::class);
        $this->setAside(new ProbeMessage('middle'), 'second', JobSetAsideException::class);
        $this->setAside(new ProbeMessage('newest'), 'third', JobSetAsideException::class);

        $monitor = new BackgroundJobsMonitor($this->jobs, $this->failed, $this->getService(MessageBusInterface::class), null, $this->queues());

        self::assertSame(['third', 'second'], array_map(static fn ($job): string => $job->error, $monitor->failedJobs(2)));
    }

    /**
     * A job the shop cannot read fails the same way when replayed: the screen only
     * offers to delete it.
     */
    public function testAnUnreadableJobIsNotOfferedForReplay(): void
    {
        $this->setAside(new UndecodableJob('Vendor\\Gone\\Job', 'The class no longer exists.', '{}'), 'Unreadable');
        $this->setAside($this->mail(), 'SMTP down');

        $replayable = [];
        foreach ($this->monitor()->failedJobs() as $job) {
            $replayable[$job->error] = $job->replayable;
        }

        self::assertSame(['Unreadable' => false, 'SMTP down' => true], array_intersect_key($replayable, ['Unreadable' => 1, 'SMTP down' => 1]));
    }

    /**
     * A shop that names no queue has nothing waiting: the screen says jobs run at
     * once rather than showing an empty queue.
     */
    public function testWithoutAQueueNothingIsSaidToWait(): void
    {
        $monitor = new BackgroundJobsMonitor(new SyncTransport(static::getContainer()->get('messenger.bus.default')), $this->failed, $this->getService(MessageBusInterface::class));

        self::assertFalse($monitor->hasQueue());
        self::assertNull($monitor->pendingCount());
    }

    /**
     * Replayed, the job goes back to the transport it failed on, and leaves the
     * failure transport. Without a queue (the test configuration) it runs at once:
     * the mail is sent to the null transport of the test environment.
     */
    public function testAReplayedJobRunsAgainAndLeavesTheFailures(): void
    {
        $id = $this->setAside($this->mail(), 'SMTP down');

        self::assertTrue($this->monitor()->retry($id));

        self::assertSame(0, $this->monitor()->failedCount());
    }

    /**
     * A job that was looked at again a few times before it was set aside is replayed
     * afresh: carrying its count, it could never restart a job that failed.
     */
    public function testAReplayedExportStartsAfresh(): void
    {
        $id = $this->setAside(new RunExportJob(12, 3), 'The export failed.');
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);
                $this->dispatched[] = $envelope->getMessage();

                return $envelope;
            }
        };

        (new BackgroundJobsMonitor($this->jobs, $this->failed, $bus))->retry($id);

        self::assertEquals([new RunExportJob(12)], $bus->dispatched);
    }

    public function testAReplayThatFailsAgainKeepsTheJobAside(): void
    {
        // Nothing handles a probe: running it again fails again.
        $id = $this->setAside(new ProbeMessage('nobody handles me'), 'No handler');

        try {
            $this->monitor()->retry($id);
            self::fail('The second failure must reach the caller.');
        } catch (\Throwable) {
        }

        self::assertSame(1, $this->monitor()->failedCount());
    }

    /**
     * Replayed, a job the shop cannot read fails the same way: whatever is posted, it
     * is not replayed.
     */
    public function testAnUnreadableJobIsNeverReplayed(): void
    {
        $id = $this->setAside(new UndecodableJob('Vendor\\Gone\\Job', 'The class no longer exists.', '{}'), 'Unreadable', JobSetAsideException::class);

        self::assertFalse($this->monitor()->retry($id));
        self::assertSame(1, $this->monitor()->failedCount());
    }

    public function testADeletedJobIsGone(): void
    {
        $id = $this->setAside($this->mail(), 'SMTP down');

        self::assertTrue($this->monitor()->remove($id));
        self::assertSame(0, $this->monitor()->failedCount());
        self::assertFalse($this->monitor()->remove($id));
        self::assertFalse($this->monitor()->retry($id));
    }

    /**
     * A failed job whose class can no longer be read (a module turned off) is listed
     * and deleted like any other, instead of failing the screen.
     */
    public function testAnUnreadableFailedJobIsListedAndCanBeDeleted(): void
    {
        $id = $this->setAside(new UndecodableJob('RemovedModule\\Message\\SyncStock', 'The message class is not one the shop queues.', '{}'), 'The job cannot be read');

        $jobs = $this->monitor()->failedJobs();
        self::assertSame('RemovedModule\\Message\\SyncStock', $jobs[0]->messageClass);
        self::assertStringStartsWith('Unreadable job', $jobs[0]->description);

        self::assertTrue($this->monitor()->remove($id));
        self::assertSame(0, $this->monitor()->failedCount());
    }

    /**
     * The queue is down: the replay fails, and so does setting the job aside again.
     * The caller learns the job is lost, and the log names it and both causes rather
     * than losing it without a word; their texts, which may quote a customer, stay out.
     */
    public function testAReplayThatCannotPutTheJobBackSaysItIsLost(): void
    {
        $id = $this->setAside($this->mail(), 'SMTP down');
        $refusingBus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \RuntimeException('The queue server is unreachable.');
            }
        };
        $failedThatRefusesWrites = new class($this->failed) implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface {
            public function __construct(private readonly DoctrineTransport $inner)
            {
            }

            public function get(): iterable
            {
                return $this->inner->get();
            }

            public function ack(Envelope $envelope): void
            {
                $this->inner->ack($envelope);
            }

            public function reject(Envelope $envelope): void
            {
                $this->inner->reject($envelope);
            }

            public function send(Envelope $envelope): Envelope
            {
                throw new \RuntimeException('The database is read only.');
            }

            public function all(?int $limit = null): iterable
            {
                return $this->inner->all($limit);
            }

            public function find(mixed $id): ?Envelope
            {
                return $this->inner->find($id);
            }

            public function getMessageCount(): int
            {
                return $this->inner->getMessageCount();
            }
        };

        try {
            (new BackgroundJobsMonitor($this->jobs, $failedThatRefusesWrites, $refusingBus))->retry($id);
            self::fail('The caller must learn the job is neither replayed nor set aside.');
        } catch (\RuntimeException $exception) {
            // The caller learns the job is lost; why goes to the log, by class and place.
            self::assertStringContainsString('lost from the queues', $exception->getMessage());
            self::assertStringNotContainsString('unreachable', $exception->getMessage());
            self::assertStringNotContainsString('read only', $exception->getMessage());
        }
    }

    private function monitor(): BackgroundJobsMonitor
    {
        return new BackgroundJobsMonitor($this->jobs, $this->failed, $this->getService(MessageBusInterface::class));
    }

    private function queues(string $jobDsn = 'doctrine://default?queue_name=test_monitor_jobs', string $heavyDsn = 'doctrine://default?queue_name=test_monitor_heavy'): ConfiguredQueues
    {
        return new ConfiguredQueues($this->getService(ShopDatabaseConnection::class), $jobDsn, $heavyDsn, 'doctrine://default?queue_name=test_monitor_failed');
    }

    /**
     * @param class-string<\Throwable> $exceptionClass what the job threw: a mail refused by
     *                                                 the server unless said otherwise
     */
    private function setAside(object $message, string $reason, string $exceptionClass = MailerTransportException::class): string
    {
        $this->failed->send(new Envelope($message, [
            new SentToFailureTransportStamp('async'),
            new RedeliveryStamp(0, new \DateTimeImmutable('-1 hour')),
            new ErrorDetailsStamp($exceptionClass, 0, $reason),
        ]));

        $jobs = $this->monitor()->failedJobs();
        self::assertNotSame([], $jobs);

        return $jobs[0]->id;
    }

    private function mail(): SendEmailMessage
    {
        return new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Your order ORD000000000042')->text('Thank you.'));
    }

    private function transport(string $queue): DoctrineTransport
    {
        $transport = $this->getService(ShopDatabaseTransportFactory::class)->createTransport(
            'doctrine://default?queue_name='.$queue,
            [],
            ProbeSerializer::create(static::getContainer()),
        );
        \assert($transport instanceof DoctrineTransport);

        return $transport;
    }

    private function empty(): void
    {
        foreach ([$this->jobs, $this->failed] as $transport) {
            foreach ($transport->all() as $envelope) {
                $transport->reject($envelope);
            }
        }
    }
}
