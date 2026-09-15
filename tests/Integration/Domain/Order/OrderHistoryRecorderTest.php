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
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\EventListener\RecordOrderHistoryListener;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class OrderHistoryRecorderTest extends ActionIntegrationTestCase
{
    public function testAStatusChangeIsRecordedWithBothStatusCodes(): void
    {
        $order = $this->factory->order();

        $this->changeStatus($order, OrderStatus::CODE_PAID);

        $entries = $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED);
        self::assertCount(1, $entries);

        self::assertSame(
            ['from' => OrderStatus::CODE_NOT_PAID, 'to' => OrderStatus::CODE_PAID],
            $entries[0]->getDecodedPayload(),
        );
    }

    public function testAnEventDispatchedOutsideAnyHttpSessionIsAuthoredByTheSystem(): void
    {
        $order = $this->factory->order();

        $this->changeStatus($order, OrderStatus::CODE_PAID);

        $entry = $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED)[0];

        self::assertSame(OrderHistoryActorType::SYSTEM->value, $entry->getActorType());
        self::assertNull($entry->getActorLabel());
        self::assertNull($entry->getAdminId());
        self::assertSame(0, $entry->getVisibleToCustomer());
    }

    public function testTwoConcurrentNotificationsOfTheSameTransitionLeaveASingleLine(): void
    {
        $order = $this->factory->order();
        $paidStatusId = $this->statusId(OrderStatus::CODE_PAID);

        // Both events are built before either is handled, each on its own instance of
        // the order — which is what two notifications of the same payment look like
        // when they arrive at the same time, both still seeing the order as unpaid.
        $firstOrder = OrderQuery::create()->findPk($order->getId());
        OrderTableMap::clearInstancePool();
        $secondOrder = OrderQuery::create()->findPk($order->getId());

        self::assertNotSame($firstOrder, $secondOrder);

        $firstEvent = new OrderEvent($firstOrder);
        $firstEvent->setStatus($paidStatusId);
        $this->dispatch($firstEvent, TheliaEvents::ORDER_UPDATE_STATUS);

        $secondEvent = new OrderEvent($secondOrder);
        $secondEvent->setStatus($paidStatusId);
        $this->dispatch($secondEvent, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertCount(1, $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED));
    }

    public function testAStatusResubmittedUnchangedWritesNothing(): void
    {
        $order = $this->factory->order();

        $this->changeStatus($order, OrderStatus::CODE_NOT_PAID);

        self::assertCount(0, $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED));
    }

    public function testTwoDistinctTransitionsAreBothKept(): void
    {
        $order = $this->factory->order();

        $this->changeStatus($order, OrderStatus::CODE_PAID);
        $this->changeStatus($order, OrderStatus::CODE_SENT);

        $entries = $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED);
        self::assertCount(2, $entries);
        self::assertSame(OrderStatus::CODE_PAID, $entries[1]->getDecodedPayload()['to']);
    }

    public function testTheModuleThatDispatchedTheChangeIsNamedAsTheAuthor(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent($order);
        $event
            ->setStatus($this->statusId(OrderStatus::CODE_PAID))
            ->setSourceModuleCode('Cheque');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        $entry = $this->entriesOf($order, OrderHistoryEventType::STATUS_CHANGED)[0];

        self::assertSame(OrderHistoryActorType::MODULE->value, $entry->getActorType());
        self::assertSame('Cheque', $entry->getActorLabel());
    }

    public function testADeliveryReferenceUpdateIsRecordedWithTheReference(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent($order);
        $event->setDeliveryRef('TRACK-98765');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);

        $entries = $this->entriesOf($order, OrderHistoryEventType::DELIVERY_REF_UPDATED);
        self::assertCount(1, $entries);
        self::assertSame(['delivery_ref' => 'TRACK-98765'], $entries[0]->getDecodedPayload());
    }

    public function testATransactionReferenceUpdateIsRecordedWithTheReference(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent($order);
        $event->setTransactionRef('TXN-55555');
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_TRANSACTION_REF);

        $entries = $this->entriesOf($order, OrderHistoryEventType::TRANSACTION_REF_UPDATED);
        self::assertCount(1, $entries);
        self::assertSame(['transaction_ref' => 'TXN-55555'], $entries[0]->getDecodedPayload());
    }

    public function testAnAddressUpdateNamesWhichOfTheTwoAddressesChanged(): void
    {
        $order = $this->factory->order();
        $invoiceAddressId = $order->getInvoiceOrderAddressId();
        $orderAddress = OrderAddressQuery::create()->findPk($invoiceAddressId);
        self::assertNotNull($orderAddress);

        $event = new OrderAddressEvent(
            $orderAddress->getCustomerTitleId(),
            $orderAddress->getFirstname(),
            'Historised',
            $orderAddress->getAddress1(),
            $orderAddress->getAddress2(),
            $orderAddress->getAddress3(),
            $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            $orderAddress->getCountryId(),
            $orderAddress->getPhone(),
            $orderAddress->getCompany(),
            $orderAddress->getCellphone(),
            $orderAddress->getStateId(),
        );
        $event->setOrderAddress($orderAddress);
        $event->setOrder($order);

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);

        $entries = $this->entriesOf($order, OrderHistoryEventType::ADDRESS_UPDATED);
        self::assertCount(1, $entries);
        self::assertSame(
            ['order_address_id' => $invoiceAddressId, 'address_type' => 'invoice'],
            $entries[0]->getDecodedPayload(),
        );
    }

    public function testAnAddressEventWithoutItsOrderRecordsNothingRatherThanFailing(): void
    {
        $order = $this->factory->order();
        $orderAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        self::assertNotNull($orderAddress);

        $event = new OrderAddressEvent(
            $orderAddress->getCustomerTitleId(),
            $orderAddress->getFirstname(),
            'Orphan',
            $orderAddress->getAddress1(),
            $orderAddress->getAddress2(),
            $orderAddress->getAddress3(),
            $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            $orderAddress->getCountryId(),
            $orderAddress->getPhone(),
            $orderAddress->getCompany(),
            $orderAddress->getCellphone(),
            $orderAddress->getStateId(),
        );
        $event->setOrderAddress($orderAddress);

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);

        self::assertCount(0, $this->entriesOf($order, OrderHistoryEventType::ADDRESS_UPDATED));
    }

    /**
     * ORDER_PAY is dispatched from a full checkout, which no unit of this suite builds:
     * the listener is driven directly, on the event Thelia\Action\Order::create() hands
     * it once the order is placed.
     */
    public function testAPlacedOrderIsRecordedAsCreatedWithItsReference(): void
    {
        $order = $this->factory->order();

        $event = new OrderEvent(new Order());
        $event->setPlacedOrder($order);

        $this->getService(RecordOrderHistoryListener::class)->onOrderPaid($event);

        $entries = $this->entriesOf($order, OrderHistoryEventType::ORDER_CREATED);
        self::assertCount(1, $entries);
        self::assertSame(['order_ref' => $order->getRef()], $entries[0]->getDecodedPayload());
    }

    public function testAnOrderPayEventThatNeverPlacedAnOrderRecordsNothing(): void
    {
        $order = $this->factory->order();

        $this->getService(RecordOrderHistoryListener::class)->onOrderPaid(new OrderEvent($order));

        self::assertCount(0, $this->entriesOf($order, OrderHistoryEventType::ORDER_CREATED));
    }

    public function testTwoIdenticalNotesAreBothKept(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordNote($order->getId(), 'Called the customer back.', visibleToCustomer: true);
        $recorder->recordNote($order->getId(), 'Called the customer back.', visibleToCustomer: true);

        $entries = $this->entriesOf($order, OrderHistoryEventType::NOTE);
        self::assertCount(2, $entries);
        self::assertSame('Called the customer back.', $entries[0]->getComment());
        self::assertSame(1, $entries[0]->getVisibleToCustomer());
    }

    public function testRecordingOnAnUnknownOrderIsSwallowed(): void
    {
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordNote(2147483646, 'Order that does not exist');

        self::assertCount(
            0,
            OrderHistoryQuery::create()->filterByOrderId(2147483646)->find()->getData(),
        );
    }

    private function changeStatus(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->statusId($statusCode));

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function statusId(string $statusCode): int
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status, "Seeded order status '$statusCode' is missing — run bin/test-prepare.");

        return $status->getId();
    }

    /**
     * @return list<OrderHistory>
     */
    private function entriesOf(Order $order, OrderHistoryEventType $eventType): array
    {
        return array_values(
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType($eventType->value)
                ->orderById(Criteria::DESC)
                ->find()
                ->getData(),
        );
    }
}
