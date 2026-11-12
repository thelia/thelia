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

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * The work a caller hands the status change, run by the core in the transaction that writes
 * the status, under the row lock of the order: what the admin API writes with a new status.
 */
final class OrderStatusWriteWithStatusTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    public function testTheWorkRunsUnderTheLockBeforeTheStatusIsWrittenAndTheListenersSeeIt(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_NOT_PAID]);
        $connection = $this->getPropelConnection();
        $depthOutside = $connection->getNestedTransactionCount();
        $seenByTheWork = null;
        $deliveryRefSeenAfterTheCore = null;
        $listener = static function (OrderEvent $event) use (&$deliveryRefSeenAfterTheCore): void {
            $deliveryRefSeenAfterTheCore = $event->getOrder()->getDeliveryRef();
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener, 32);

        $event = (new OrderEvent($order))
            ->setStatus($this->orderStatus(OrderStatus::CODE_PAID)->getId())
            ->writeWithStatus(static function (int $lockedStatusId) use ($order, $connection, &$seenByTheWork): void {
                $statement = $connection->prepare('SELECT `status_id` FROM `order` WHERE `id` = ?');
                $statement->execute([$order->getId()]);
                $seenByTheWork = [$lockedStatusId, (int) $statement->fetchColumn(), $connection->getNestedTransactionCount()];

                $statement = $connection->prepare('UPDATE `order` SET `delivery_ref` = ? WHERE `id` = ?');
                $statement->execute(['TRACK-WITH-STATUS', $order->getId()]);
            });

        try {
            $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener);
        }

        $notPaidId = $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId();
        self::assertSame([$notPaidId, $notPaidId, $depthOutside + 1], $seenByTheWork, 'The work runs inside the transaction of the core, before the status is written.');
        self::assertSame('TRACK-WITH-STATUS', $deliveryRefSeenAfterTheCore, 'The listeners after the core see what the work wrote.');
        self::assertSame($notPaidId, $event->getPreviousStatusId());
        OrderTableMap::clearInstancePool();
        self::assertSame(OrderStatus::CODE_PAID, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode());
    }

    public function testARefusedTransitionRunsNoWork(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets(
            $this->orderStatus(OrderStatus::CODE_SENT)->getId(),
            [$this->orderStatus(OrderStatus::CODE_REFUNDED)->getId()],
        );
        $workRan = false;

        $event = (new OrderEvent($order))
            ->setStatus($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId())
            ->writeWithStatus(static function () use (&$workRan): void {
                $workRan = true;
            });

        try {
            $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
            self::fail('The graph refuses the transition.');
        } catch (OrderStatusTransitionRefusedException) {
        }

        self::assertFalse($workRan);
    }

    public function testAnOrderAlreadyAtTheTargetRunsTheWorkAloneAndStopsTheEvent(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $paidId = $this->orderStatus(OrderStatus::CODE_PAID)->getId();
        $workRanWith = null;
        $listenerRan = false;
        $listener = static function () use (&$listenerRan): void {
            $listenerRan = true;
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener, 32);

        $event = (new OrderEvent($order))
            ->setStatus($paidId)
            ->writeWithStatus(static function (int $lockedStatusId) use (&$workRanWith): void {
                $workRanWith = $lockedStatusId;
            });

        try {
            $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener);
        }

        self::assertSame($paidId, $workRanWith);
        self::assertFalse($listenerRan, 'No transition happened: the listeners of one do not run.');
        self::assertTrue($event->isPropagationStopped());
        self::assertNull($event->getPreviousStatusId());
    }
}
