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
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Order\Service\OrderStatusTransitionGraphProvider;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A status change the transition graph refuses leaves the history untouched.
 *
 * The history is written by two listeners on ORDER_UPDATE_STATUS, one reading the
 * old status ahead of the core listener and one writing the line behind it. The
 * guard refuses from inside the core listener, between the two, so the refusal has
 * to reach the dispatcher before the second one runs. Nothing in the listeners says
 * so on its own — it holds only because of where the guard is called from — and a
 * history claiming a status change that the shop refused is worse than no history
 * at all.
 */
final class OrderHistoryRefusedTransitionTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        // The rows go back with the wrapper transaction; the graph the provider
        // built from them stays in the service for the rest of the process.
        $this->getService(OrderStatusTransitionGraphProvider::class)->reset();

        parent::tearDown();
    }

    public function testARefusedTransitionWritesNoHistoryLine(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $linesBefore = $this->statusLinesOf($order);
        $refused = false;

        try {
            $this->changeStatus($order, OrderStatus::CODE_NOT_PAID);
        } catch (OrderStatusTransitionRefusedException) {
            $refused = true;
        }

        // The history is asserted before the refusal itself: a guard that stopped
        // guarding must be reported as the line it let through, which is what this
        // test exists for, not as a missing exception.
        self::assertSame(
            $linesBefore,
            $this->statusLinesOf($order),
            'A refused status change must leave no line in the order history.',
        );
        self::assertTrue($refused, 'The graph allows "sent" to reach "refunded" only: the change had to be refused.');
        self::assertSame(
            OrderStatus::CODE_SENT,
            OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode(),
            'A refused status change must leave the order on its status.',
        );
    }

    public function testTheTransitionTheGraphAllowsIsStillRecorded(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->allowOnly(OrderStatus::CODE_SENT, [OrderStatus::CODE_REFUNDED]);

        $this->changeStatus($order, OrderStatus::CODE_REFUNDED);

        $entry = OrderHistoryQuery::create()
            ->findLatestOfType($order->getId(), OrderHistoryEventType::STATUS_CHANGED->value);

        self::assertNotNull($entry, 'An allowed status change is recorded the way it always was.');
        self::assertSame(
            ['from' => OrderStatus::CODE_SENT, 'to' => OrderStatus::CODE_REFUNDED],
            $entry->getDecodedPayload(),
        );
    }

    private function statusLinesOf(Order $order): int
    {
        return OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->count();
    }

    private function changeStatus(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus($statusCode)->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    /**
     * @param list<string> $toCodes
     */
    private function allowOnly(string $fromCode, array $toCodes): void
    {
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets(
            $this->orderStatus($fromCode)->getId(),
            array_map(fn (string $code): int => $this->orderStatus($code)->getId(), $toCodes),
        );
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Seeded order status '$code' is missing.");

        return $status;
    }
}
