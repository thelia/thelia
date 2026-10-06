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

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Mime\Email;
use Thelia\Messenger\Message\UndecodableJob;
use Thelia\Messenger\Monitoring\BackgroundJobsMonitor;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;
use Thelia\Messenger\Transport\ShopDatabaseTransportFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;

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

    public function testTheJobsWaitingForAWorkerAreCounted(): void
    {
        $this->jobs->send(new Envelope(new ProbeMessage('waiting')));
        $this->jobs->send(new Envelope(new ProbeMessage('waiting too')));

        self::assertTrue($this->monitor()->hasQueue());
        self::assertSame(2, $this->monitor()->pendingCount());
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

    private function monitor(): BackgroundJobsMonitor
    {
        return new BackgroundJobsMonitor($this->jobs, $this->failed, $this->getService(MessageBusInterface::class));
    }

    private function setAside(object $message, string $reason): string
    {
        $this->failed->send(new Envelope($message, [
            new SentToFailureTransportStamp('async'),
            new RedeliveryStamp(0, new \DateTimeImmutable('-1 hour')),
            new ErrorDetailsStamp(\RuntimeException::class, 0, $reason),
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
            $this->getService(AllowedClassesSerializer::class),
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
