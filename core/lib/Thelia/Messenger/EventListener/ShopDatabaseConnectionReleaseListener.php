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

namespace Thelia\Messenger\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Messenger\Transport\ShopDatabaseConnection;

/**
 * Lets the queue connection go once a job is over, before the worker acknowledges it.
 *
 * Messenger dispatches these events before the ack, so the ack, or the retry, goes
 * through a connection opened afresh: one the server cannot have closed while a long
 * job kept the worker busy.
 */
final readonly class ShopDatabaseConnectionReleaseListener
{
    public function __construct(
        private ShopDatabaseConnection $connection,
    ) {
    }

    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function onJobOver(): void
    {
        $this->connection->close();
    }
}
