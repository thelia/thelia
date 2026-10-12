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

use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Envelope as MailEnvelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface as MailTransportInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Test\IntegrationTestCase;

/**
 * A worker of Symfony, on queues held in memory, with the retry strategies and the
 * serializer of the shop: what becomes of a job that keeps failing.
 */
final class FailedJobRetryChainTest extends IntegrationTestCase
{
    /**
     * A mail the server refuses is delivered four times in all, 30 seconds, 2 minutes
     * then 8 minutes apart, then set aside with what the server answered each time.
     */
    public function testAMailTheServerRefusesIsTriedFourTimesThenSetAsideWithWhy(): void
    {
        $server = $this->refusingMailServer();
        $clock = new MockClock();
        $jobs = new InMemoryTransport($this->serializer(), $clock);
        $failed = new InMemoryTransport($this->serializer(), $clock);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([SendEmailMessage::class => [new MessageHandler($server)]]))]);
        $jobs->send(new Envelope(new SendEmailMessage((new Email())->from('shop@example.com')->to('buyer@example.com')->subject('Order')->text('Thank you'))));

        $this->worker(['async' => $jobs], $failed, $bus, $clock)->run(['sleep' => 0]);

        self::assertSame(4, $server->attempts, 'The first delivery and three retries.');
        $delays = array_map(static fn (Envelope $sent): ?int => $sent->last(DelayStamp::class)?->getDelay(), $jobs->getSent());
        self::assertNull($delays[0], 'The first delivery waits for nothing.');
        // Symfony spreads each delay by up to a tenth either way.
        foreach ([1 => 30000, 2 => 120000, 3 => 480000] as $delivery => $delay) {
            self::assertGreaterThanOrEqual((int) ($delay * 0.9), $delays[$delivery] ?? 0, \sprintf('The delay before delivery %d.', $delivery + 1));
            self::assertLessThanOrEqual((int) ($delay * 1.1), $delays[$delivery] ?? 0, \sprintf('The delay before delivery %d.', $delivery + 1));
        }
        self::assertSame([], iterator_to_array($jobs->get()), 'Nothing waits in the queue of the jobs any more.');

        $setAside = $failed->getSent();
        self::assertCount(1, $setAside);
        self::assertSame('async', $setAside[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        // Symfony keeps one record of an error that repeats itself word for word.
        self::assertNotEmpty($setAside[0]->all(ErrorDetailsStamp::class), 'What the server answered is kept with the job.');
        self::assertSame(TransportException::class, $setAside[0]->last(ErrorDetailsStamp::class)?->getExceptionClass());
        self::assertSame(3, max(array_map(static fn (RedeliveryStamp $stamp): int => $stamp->getRetryCount(), $setAside[0]->all(RedeliveryStamp::class))));
        self::assertInstanceOf(SendEmailMessage::class, $setAside[0]->getMessage());
    }

    /**
     * An export or an import that fails is set aside at once: running a whole import
     * again on its own is the administrator's call, from the failed jobs.
     */
    public function testAnExportThatFailsIsSetAsideAtOnce(): void
    {
        $attempts = 0;
        $clock = new MockClock();
        $jobs = new InMemoryTransport($this->serializer(), $clock);
        $failed = new InMemoryTransport($this->serializer(), $clock);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([RunExportJob::class => [static function (RunExportJob $job) use (&$attempts): void {
            ++$attempts;

            throw new \RuntimeException('No space left on device.');
        }]]))]);
        $jobs->send(new Envelope(new RunExportJob(7)));

        $this->worker(['async_heavy' => $jobs], $failed, $bus, $clock)->run(['sleep' => 0]);

        self::assertSame(1, $attempts);
        self::assertCount(1, $jobs->getSent(), 'The job was never sent back for a retry.');
        self::assertSame([], iterator_to_array($jobs->get()));

        $setAside = $failed->getSent();
        self::assertCount(1, $setAside);
        self::assertSame('async_heavy', $setAside[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        self::assertCount(1, $setAside[0]->all(ErrorDetailsStamp::class));
        self::assertSame(\RuntimeException::class, $setAside[0]->last(ErrorDetailsStamp::class)?->getExceptionClass());
        $message = $setAside[0]->getMessage();
        self::assertInstanceOf(RunExportJob::class, $message);
        self::assertSame(7, $message->exportJobId);
    }

    /**
     * The listeners Symfony runs on a failed job, with the retry strategies of the shop
     * and $failed standing for the failure transport. Each time the worker finds nothing
     * to do, the clock moves ten minutes on, past the longest delay before a retry; the
     * worker stops when it still finds nothing, or after ten jobs if a retry never ends.
     *
     * @param array<string, InMemoryTransport> $transports by the name of the transport
     */
    private function worker(array $transports, InMemoryTransport $failed, MessageBusInterface $bus, MockClock $clock): Worker
    {
        $retryStrategies = static::getContainer()->get('messenger.retry_strategy_locator');
        \assert($retryStrategies instanceof \Psr\Container\ContainerInterface);
        $senders = new ServiceLocator(array_map(static fn (InMemoryTransport $transport): \Closure => static fn (): InMemoryTransport => $transport, $transports));
        $failureSenders = new ServiceLocator(array_map(static fn (): \Closure => static fn (): InMemoryTransport => $failed, $transports));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $retryStrategies));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureSenders));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(10));
        $idleRounds = 0;
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock, &$idleRounds): void {
            if (!$event->isWorkerIdle()) {
                $idleRounds = 0;

                return;
            }

            if (++$idleRounds > 1) {
                $event->getWorker()->stop();

                return;
            }

            $clock->sleep(600);
        });

        return new Worker($transports, $bus, $dispatcher);
    }

    private function serializer(): SerializerInterface
    {
        $serializer = static::getContainer()->get('messenger.default_serializer');
        \assert($serializer instanceof SerializerInterface);

        return $serializer;
    }

    /**
     * @return MailTransportInterface&object{attempts: int}
     */
    private function refusingMailServer(): MailTransportInterface
    {
        return new class implements MailTransportInterface {
            public int $attempts = 0;

            public function send(RawMessage $message, ?MailEnvelope $envelope = null): ?SentMessage
            {
                ++$this->attempts;

                throw new TransportException('Connection could not be established with host "mail.example.com:587": Connection refused.');
            }

            public function __toString(): string
            {
                return 'smtp://mail.example.com:587';
            }
        };
    }
}
