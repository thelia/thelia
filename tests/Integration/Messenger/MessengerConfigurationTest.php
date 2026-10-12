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

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Mime\Email;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Test\IntegrationTestCase;

/**
 * What a shop gets from the core configuration, with MESSENGER_TRANSPORT_DSN left
 * empty as a fresh install leaves it.
 */
final class MessengerConfigurationTest extends IntegrationTestCase
{
    /**
     * A shop that names no queue runs every job in the request that dispatched it, as
     * before it had a queue: nothing waits for a worker that is not there.
     */
    public function testWithoutAQueueTheJobsRunAtOnce(): void
    {
        self::assertInstanceOf(SyncTransport::class, static::getContainer()->get('messenger.transport.async'));
    }

    public function testTheJobsThatFailedForGoodAreKeptInTheShopDatabase(): void
    {
        self::assertInstanceOf(DoctrineTransport::class, static::getContainer()->get('messenger.transport.failed'));
    }

    public function testTheDeliveryOfAMailGoesThroughTheJobTransport(): void
    {
        $locator = static::getContainer()->get('messenger.senders_locator');
        \assert($locator instanceof SendersLocatorInterface);

        $senders = iterator_to_array($locator->getSenders(new Envelope(new SendEmailMessage((new Email())->to('buyer@example.com')->text('Hello')))));

        self::assertSame(['async'], array_keys($senders));
    }

    public function testTheExportsAndImportsGoThroughTheHeavyJobTransport(): void
    {
        $locator = static::getContainer()->get('messenger.senders_locator');
        \assert($locator instanceof SendersLocatorInterface);

        self::assertSame(['async_heavy'], array_keys(iterator_to_array($locator->getSenders(new Envelope(new RunExportJob(1))))));
        self::assertSame(['async_heavy'], array_keys(iterator_to_array($locator->getSenders(new Envelope(new RunImportJob(1))))));
    }

    public function testWithoutAQueueTheHeavyJobsRunAtOnceToo(): void
    {
        self::assertInstanceOf(SyncTransport::class, static::getContainer()->get('messenger.transport.async_heavy'));
    }

    /**
     * A heavy job that fails is set aside at once, whatever failed: an import is not
     * run three more times on its own.
     */
    public function testAHeavyJobIsNeverRetriedOnItsOwn(): void
    {
        self::assertFalse($this->retryStrategyOf('async_heavy')->isRetryable(new Envelope(new RunImportJob(1)), new \RuntimeException('Connection lost.')));
    }

    /**
     * A mail the server refuses is tried three more times, 30 seconds, 2 minutes then 8
     * minutes apart, which outlasts a mail server restarting. Then it is set aside.
     */
    public function testAJobIsTriedThreeMoreTimesWithGrowingDelaysThenSetAside(): void
    {
        $strategy = $this->retryStrategyOf('async');
        $refused = new TransportException('Connection refused.');

        foreach ([0 => 30000, 1 => 120000, 2 => 480000] as $retriesMade => $delay) {
            $envelope = $this->mailRetried($retriesMade);

            self::assertTrue($strategy->isRetryable($envelope, $refused), \sprintf('After %d retries the job is tried again.', $retriesMade));
            // Symfony spreads the delays by up to a tenth either way, so that jobs that
            // failed together are not all retried at the same instant.
            $waited = $strategy->getWaitingTime($envelope, $refused);
            self::assertGreaterThanOrEqual((int) ($delay * 0.9), $waited, \sprintf('The delay of retry %d.', $retriesMade + 1));
            self::assertLessThanOrEqual((int) ($delay * 1.1), $waited, \sprintf('The delay of retry %d.', $retriesMade + 1));
        }

        self::assertFalse($strategy->isRetryable($this->mailRetried(3), $refused), 'After three retries the job is set aside.');
    }

    private function mailRetried(int $times): Envelope
    {
        $mail = new SendEmailMessage((new Email())->to('buyer@example.com')->text('Hello'));

        return new Envelope($mail, 0 === $times ? [] : [new RedeliveryStamp($times)]);
    }

    private function retryStrategyOf(string $transport): RetryStrategyInterface
    {
        $strategies = static::getContainer()->get('messenger.retry_strategy_locator');
        \assert($strategies instanceof \Psr\Container\ContainerInterface);
        $strategy = $strategies->get($transport);
        \assert($strategy instanceof RetryStrategyInterface);

        return $strategy;
    }
}
