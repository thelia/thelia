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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\Event\OrderHistoryEvent;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A history line is an order_history row like any other: a module listening to the
 * Propel model events of that table (propel.pre.insert / post.insert / post.save) is told
 * when a line is written, as it was when the recorder went through OrderHistory::save().
 */
final class OrderHistoryModelEventsTest extends ActionIntegrationTestCase
{
    public function testWritingAHistoryLineStillDispatchesThePropelModelEventsOfTheTable(): void
    {
        $order = $this->factory->order();
        $seen = [];

        $listener = static function (OrderHistoryEvent $event, string $eventName) use (&$seen): void {
            $seen[] = [$eventName, $event->getModel()->getOrderId()];
        };

        $this->dispatcher->addListener(OrderHistoryEvent::PRE_INSERT, $listener);
        $this->dispatcher->addListener(OrderHistoryEvent::POST_INSERT, $listener);
        $this->dispatcher->addListener(OrderHistoryEvent::POST_SAVE, $listener);

        try {
            $this->getService(OrderHistoryRecorder::class)->recordNote($order->getId(), 'Parcel left at the post office.');
        } finally {
            $this->dispatcher->removeListener(OrderHistoryEvent::PRE_INSERT, $listener);
            $this->dispatcher->removeListener(OrderHistoryEvent::POST_INSERT, $listener);
            $this->dispatcher->removeListener(OrderHistoryEvent::POST_SAVE, $listener);
        }

        self::assertSame(
            [
                [OrderHistoryEvent::PRE_INSERT, $order->getId()],
                [OrderHistoryEvent::POST_INSERT, $order->getId()],
                [OrderHistoryEvent::POST_SAVE, $order->getId()],
            ],
            $seen,
            'A module listening to the model events of order_history is no longer told that a line was written.',
        );
    }
}
