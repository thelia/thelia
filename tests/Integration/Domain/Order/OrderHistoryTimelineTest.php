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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\EventListener\RecordOrderHistoryListener;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The order the entries of one order are written in.
 *
 * A history that opens on the order being paid and mentions it being created
 * afterwards is unreadable, and that is exactly what a shop with an immediate
 * payment method got: the payment module confirms the payment from inside the
 * ORDER_PAY dispatch, so the status change was written before the listener
 * further down that same dispatch had written the creation.
 */
final class OrderHistoryTimelineTest extends ActionIntegrationTestCase
{
    public function testAnOrderPaidOnTheSpotIsCreatedBeforeItIsPaid(): void
    {
        $order = $this->factory->order();

        // What Thelia\Action\Order::create() does, in its order: the order is placed
        // and announced, then the payment module is called and confirms on the spot.
        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);
        $this->changeStatus($order, OrderStatus::CODE_PAID);

        $creation = $this->latestEntry($order, OrderHistoryEventType::ORDER_CREATED);
        $statusChange = $this->latestEntry($order, OrderHistoryEventType::STATUS_CHANGED);

        self::assertNotNull($creation, 'Placing an order must open its history.');
        self::assertNotNull($statusChange, 'Paying the order must add a line to it.');

        self::assertLessThan(
            $statusChange->getId(),
            $creation->getId(),
            'The order was created before it was paid, and its history has to say so.',
        );
    }

    public function testTheCreationLineCarriesTheOrderReference(): void
    {
        $order = $this->factory->order();

        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        $creation = $this->latestEntry($order, OrderHistoryEventType::ORDER_CREATED);

        self::assertNotNull($creation);
        self::assertSame(['order_ref' => $order->getRef()], $creation->getDecodedPayload());
    }

    /**
     * The payment path still raises ORDER_PAY after the order was placed, and the
     * listener still answers it for the sake of an order placed some other way. The
     * two together must leave one creation line, not two.
     */
    public function testPlacingThenPayingLeavesASingleCreationLine(): void
    {
        $order = $this->factory->order();

        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        // The listener is driven directly rather than through the dispatcher: the core
        // listener on ORDER_PAY places a whole order of its own from the session, which
        // no unit of this suite has.
        $payEvent = new OrderEvent(new Order());
        $payEvent->setPlacedOrder($order);
        $this->getService(RecordOrderHistoryListener::class)->onOrderPaid($payEvent);

        self::assertCount(
            1,
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::ORDER_CREATED->value)
                ->find()
                ->getData(),
        );
    }

    private function changeStatus(Order $order, string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status, "Seeded order status '$statusCode' is missing — run bin/test-prepare.");

        $event = new OrderEvent($order);
        $event->setStatus($status->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function latestEntry(Order $order, OrderHistoryEventType $eventType): ?OrderHistory
    {
        return OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType($eventType->value)
            ->orderById(Criteria::DESC)
            ->findOne();
    }
}
