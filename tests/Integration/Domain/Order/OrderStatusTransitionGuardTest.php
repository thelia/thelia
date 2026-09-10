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
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderStatusTransitionQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The transition graph is enforced where every status change lands, the
 * ORDER_UPDATE_STATUS listener of the core, so these tests drive the event
 * the way the back office, the API and the payment modules do.
 */
final class OrderStatusTransitionGuardTest extends ActionIntegrationTestCase
{
    private OrderStatusTransitionGuard $guard;

    private OrderStatusTransitionWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = $this->getService(OrderStatusTransitionGuard::class);
        $this->writer = $this->getService(OrderStatusTransitionWriter::class);
    }

    public function testWithoutAnyDeclaredTransitionEveryStatusIsReachable(): void
    {
        self::assertSame(0, OrderStatusTransitionQuery::create()->count(), 'A fresh install declares no transition.');

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);

        $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);

        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testADeclaredTransitionRestrictsWhereAnOrderCanGo(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_NOT_PAID);
            self::fail('The transition sent -> not_paid should have been refused.');
        } catch (OrderStatusTransitionRefusedException $exception) {
            self::assertSame(OrderStatus::CODE_SENT, $exception->getFromStatusCode());
            self::assertSame(OrderStatus::CODE_NOT_PAID, $exception->getToStatusCode());
        }

        self::assertSame(OrderStatus::CODE_SENT, $this->reload($order)->getOrderStatus()->getCode(), 'A refused transition leaves the order untouched.');

        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame(OrderStatus::CODE_REFUNDED, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testAForcedTransitionBypassesTheGraph(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);

        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId());
        $event->forceStatusTransition();
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testACustomStatusFollowsTheGraphOfTheCanonicalStatusItIsEquivalentTo(): void
    {
        $paidOnDelivery = $this->factory->orderStatus(['code' => 'paid_on_delivery', 'equivalentCode' => OrderStatus::CODE_PAID]);
        $this->allowOnly(OrderStatus::CODE_PAID, [OrderStatus::CODE_PROCESSING]);
        $order = $this->factory->order(null, ['statusCode' => 'paid_on_delivery']);

        self::assertFalse($this->guard->isAllowed($paidOnDelivery->getId(), $this->orderStatus(OrderStatus::CODE_CANCELED)->getId()));
        self::assertTrue($this->guard->isAllowed($paidOnDelivery->getId(), $this->orderStatus(OrderStatus::CODE_PROCESSING)->getId()));

        $this->expectException(OrderStatusTransitionRefusedException::class);
        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);
    }

    public function testAllowedTargetsListsOnlyWhatTheGraphAllows(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $targets = array_map(
            static fn (OrderStatus $status): string => $status->getCode(),
            $this->guard->allowedTargets($this->orderStatus(OrderStatus::CODE_SENT)->getId()),
        );

        self::assertSame([OrderStatus::CODE_REFUNDED], $targets);

        $freeTargets = $this->guard->allowedTargets($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId());
        self::assertGreaterThanOrEqual(5, \count($freeTargets), 'A free status still proposes every other status.');
        self::assertNotContains(OrderStatus::CODE_NOT_PAID, array_map(static fn (OrderStatus $status): string => $status->getCode(), $freeTargets));
    }

    public function testPartitionSeparatesTheOrdersABulkChangeMustSkip(): void
    {
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        $sentOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $paidOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $partition = $this->guard->partition([$sentOrder, $paidOrder], $this->orderStatus(OrderStatus::CODE_CANCELED)->getId());

        self::assertSame([$paidOrder->getId()], array_map(static fn (Order $order): int => $order->getId(), $partition['allowed']));
        self::assertSame([$sentOrder->getId()], array_map(static fn (Order $order): int => $order->getId(), $partition['refused']));
    }

    public function testClearingTheTargetsOfAStatusMakesItFreeAgain(): void
    {
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        self::assertFalse($this->guard->isAllowed($sent->getId(), $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()));

        $this->writer->replaceTargets($sent->getId(), []);

        self::assertTrue($this->guard->isAllowed($sent->getId(), $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()));
    }

    public function testUnreachableStatusesAreReportedWhenNoStatusIsFreeAnyMore(): void
    {
        $orphan = $this->factory->orderStatus(['code' => 'orphan_status']);

        foreach (OrderStatusQuery::create()->find() as $status) {
            $targets = $status->getId() === $orphan->getId() ? [$this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()] : [$this->orderStatus(OrderStatus::CODE_CANCELED)->getId()];
            $this->writer->replaceTargets($status->getId(), $targets);
        }
        $this->writer->replaceTargets($this->orderStatus(OrderStatus::CODE_CANCELED)->getId(), [$this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()]);

        $unreachable = array_map(static fn (OrderStatus $status): string => $status->getCode(), $this->guard->unreachableStatuses());

        self::assertContains('orphan_status', $unreachable);
        self::assertNotContains(OrderStatus::CODE_CANCELED, $unreachable);
    }

    public function testChangingAnEquivalenceThroughTheStatusEventRefreshesTheGraph(): void
    {
        $custom = $this->factory->orderStatus(['code' => 'custom_free', 'title' => 'Custom']);
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);
        self::assertTrue($this->guard->isAllowed($custom->getId(), $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()), 'A custom status without equivalence is free.');

        $event = new OrderStatusUpdateEvent($custom->getId());
        $event->setCode('custom_free')->setEquivalentCode(OrderStatus::CODE_SENT)->setColor('#000000')->setLocale('en_US')->setTitle('Custom');
        $this->dispatch($event, TheliaEvents::ORDER_STATUS_UPDATE);

        self::assertFalse($this->guard->isAllowed($custom->getId(), $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId()), 'Once equivalent to sent, the status follows the graph of sent in the same process.');
    }

    public function testDeletingAStatusTakesItsTransitionsAway(): void
    {
        $custom = $this->factory->orderStatus(['code' => 'short_lived']);
        $sent = $this->orderStatus(OrderStatus::CODE_SENT);
        $this->writer->replaceTargets($sent->getId(), [$custom->getId()]);
        $this->writer->replaceTargets($custom->getId(), [$sent->getId()]);

        $this->dispatch(new OrderStatusDeleteEvent($custom->getId()), TheliaEvents::ORDER_STATUS_DELETE);

        self::assertNull(OrderStatusQuery::create()->findPk($custom->getId()));
        self::assertSame(0, OrderStatusTransitionQuery::create()->filterByFromStatusId($custom->getId())->_or()->filterByToStatusId($custom->getId())->count());
    }

    /**
     * @param list<string> $toCodes
     */
    private function allowOnly(string $fromCode, array $toCodes): void
    {
        $this->writer->replaceTargets(
            $this->orderStatus($fromCode)->getId(),
            array_map(fn (string $code): int => $this->orderStatus($code)->getId(), $toCodes),
        );
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus($statusCode)->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Seeded order status '$code' is missing.");

        return $status;
    }

    private function reload(Order $order): Order
    {
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
