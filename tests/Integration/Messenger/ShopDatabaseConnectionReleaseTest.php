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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Thelia\Messenger\EventListener\ShopDatabaseConnectionReleaseListener;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\ProbeMessage;

/**
 * A worker acknowledges a job on a connection opened after the job, never on one a
 * long job left idle long enough for the server to close it.
 */
final class ShopDatabaseConnectionReleaseTest extends IntegrationTestCase
{
    public function testTheQueueConnectionIsLetGoOnceAJobIsHandled(): void
    {
        $connection = $this->getService(ShopDatabaseConnection::class);
        $connection->get()->executeQuery('SELECT 1');

        $this->getService(EventDispatcherInterface::class)->dispatch(new WorkerMessageHandledEvent(new Envelope(new ProbeMessage('done')), 'async'));

        self::assertFalse($connection->get()->isConnected());
        self::assertSame(1, (int) $connection->get()->fetchOne('SELECT 1'), 'The next query opens a new connection.');
    }

    /**
     * A failed job sent back for a retry goes through a connection opened afresh: the
     * connection is let go before Messenger sends it back.
     */
    public function testTheConnectionIsLetGoBeforeAFailedJobIsSentForARetry(): void
    {
        $listeners = $this->getService(EventDispatcherInterface::class)->getListeners(WorkerMessageFailedEvent::class);
        $positionOf = static function (string $class) use ($listeners): int {
            foreach ($listeners as $position => $listener) {
                $object = \is_array($listener) ? $listener[0] : $listener;

                if ($object instanceof $class) {
                    return $position;
                }
            }

            self::fail($class.' does not listen to the failed jobs.');
        };

        self::assertLessThan($positionOf(SendFailedMessageForRetryListener::class), $positionOf(ShopDatabaseConnectionReleaseListener::class));
    }

    /**
     * Dispatched for real, the failure would go on to the retry and failure listeners:
     * the listener is checked to be there, then called.
     */
    public function testTheQueueConnectionIsLetGoOnceAJobHasFailed(): void
    {
        $listener = $this->getService(ShopDatabaseConnectionReleaseListener::class);
        $registered = array_filter(
            $this->getService(EventDispatcherInterface::class)->getListeners(WorkerMessageFailedEvent::class),
            static fn (mixed $callable): bool => \is_array($callable) && $callable[0] === $listener,
        );
        self::assertCount(1, $registered);

        $connection = $this->getService(ShopDatabaseConnection::class);
        $connection->get()->executeQuery('SELECT 1');

        $listener->onJobOver();

        self::assertFalse($connection->get()->isConnected());
    }
}
