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

namespace Thelia\Tests\Unit\Scheduler;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Scheduler\EventListener\RecurringTaskFailureListener;
use Thelia\Scheduler\RecurringTaskFailures;

/**
 * A recurring task never reaches the failed jobs: its last failure is kept apart, for
 * the back office, until it runs through again.
 */
final class RecurringTaskFailureListenerTest extends TestCase
{
    private RecurringTaskFailures $failures;

    private RecurringTaskFailureListener $listener;

    protected function setUp(): void
    {
        $this->failures = new RecurringTaskFailures(new ArrayAdapter());
        $this->listener = new RecurringTaskFailureListener($this->failures, new NullLogger());
    }

    public function testAFailedTaskIsKeptWithWhy(): void
    {
        $this->listener->onFailed($this->failed('maintenance:purge', new \RuntimeException('Command "maintenance:purge" exited with code "1".')));

        $failures = $this->failures->all();
        self::assertCount(1, $failures);
        self::assertSame('maintenance:purge', $failures[0]->task);
        self::assertSame('Command "maintenance:purge" exited with code "1".', $failures[0]->error);
    }

    public function testADatabaseErrorIsNotQuoted(): void
    {
        $this->listener->onFailed($this->failed('sale:check-activation', new \PDOException('SQLSTATE[HY000] [2002] Connection refused')));

        self::assertSame(JobFailureMessage::SERVER_ERROR, $this->failures->all()[0]->error);
    }

    public function testATaskThatRunsThroughAgainIsForgotten(): void
    {
        $this->listener->onFailed($this->failed('maintenance:purge', new \RuntimeException('Failed.')));
        $this->listener->onFailed($this->failed('sale:check-activation', new \RuntimeException('Failed.')));

        $this->listener->onHandled(new WorkerMessageHandledEvent(new Envelope(new RunCommandMessage('maintenance:purge')), RecurringTaskFailureListener::RECEIVER));

        self::assertSame(['sale:check-activation'], array_map(static fn ($failure): string => $failure->task, $this->failures->all()));
    }

    public function testAJobOfAQueueIsLeftToTheFailedJobs(): void
    {
        $event = new WorkerMessageFailedEvent(new Envelope(new RunCommandMessage('maintenance:purge')), 'async', new \RuntimeException('Failed.'));

        $this->listener->onFailed($event);

        self::assertSame([], $this->failures->all());
    }

    private function failed(string $command, \Throwable $cause): WorkerMessageFailedEvent
    {
        $envelope = new Envelope(new RunCommandMessage($command));

        return new WorkerMessageFailedEvent($envelope, RecurringTaskFailureListener::RECEIVER, new HandlerFailedException($envelope, [$cause]));
    }
}
