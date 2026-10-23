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

use Cheque\Cheque;
use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * An automatic entry is dropped when it repeats the last entry of the order, whatever
 * the type of that last entry: an event that happened, then something else, then
 * happened again is a second occurrence and stays in the timeline.
 */
final class OrderHistoryDeduplicationAcrossTypesTest extends ActionIntegrationTestCase
{
    public function testTheSameMessageSentAgainAfterAStatusChangeIsKept(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordEmailSent($order->getId(), 'order_return_status_changed');
        $this->changeStatus($order, OrderStatus::CODE_PAID);

        self::assertSame(1, $this->linesCount($order, OrderHistoryEventType::STATUS_CHANGED), 'Precondition: another event happened in between.');

        $recorder->recordEmailSent($order->getId(), 'order_return_status_changed');

        self::assertSame(2, $this->linesCount($order, OrderHistoryEventType::EMAIL_SENT));
    }

    public function testTheSameMessageRepeatedRightAwayIsStillDropped(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordEmailSent($order->getId(), 'order_return_status_changed');
        $recorder->recordEmailSent($order->getId(), 'order_return_status_changed');

        self::assertSame(1, $this->linesCount($order, OrderHistoryEventType::EMAIL_SENT));
    }

    public function testADifferentPayloadOfTheSameTypeIsKept(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordEmailSent($order->getId(), 'one_message');
        $recorder->recordEmailSent($order->getId(), 'another_message');

        self::assertSame(2, $this->linesCount($order, OrderHistoryEventType::EMAIL_SENT));
    }

    /**
     * @return iterable<string, array{OrderHistoryEventType, array<string, mixed>}>
     */
    public static function valueEventsReplayedAfterAnotherLine(): iterable
    {
        yield 'delivery reference' => [OrderHistoryEventType::DELIVERY_REF_UPDATED, ['delivery_ref' => 'TRACK-1']];
        yield 'transaction reference' => [OrderHistoryEventType::TRANSACTION_REF_UPDATED, ['transaction_ref' => 'TXN-1']];
        yield 'invoice reference' => [OrderHistoryEventType::INVOICE_REF_ALLOCATED, ['invoice_ref' => '2026-000001']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('valueEventsReplayedAfterAnotherLine')]
    public function testAValueEventReplayedAfterAnotherLineIsStillDropped(OrderHistoryEventType $type, array $payload): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->record($order->getId(), $type->value, $payload);
        $recorder->recordNote($order->getId(), 'Something else happened in between.');
        $recorder->record($order->getId(), $type->value, $payload);

        self::assertSame(1, $this->linesCount($order, $type));
    }

    public function testACreationReplayedAfterAStatusChangeStillLeavesASingleLine(): void
    {
        $order = $this->factory->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        // An order paid on the spot: placed, paid inside the payment call, then
        // ORDER_PAY announces the placed order again.
        $recorder->recordOrderCreated($order->getId(), $order->getRef());
        $this->changeStatus($order, OrderStatus::CODE_PAID);
        $recorder->recordOrderCreated($order->getId(), $order->getRef());

        self::assertSame(1, $this->linesCount($order, OrderHistoryEventType::ORDER_CREATED));
    }

    public function testAStaleNotificationOfTheSameStatusAfterTheLinesTheChangeWroteIsDropped(): void
    {
        ConfigQuery::write('store_email', 'shop@example.com');
        $order = $this->factory->order();
        $order->setPaymentModuleId(Cheque::getModuleId())->save($this->getPropelConnection());

        // Paying writes the status line, then the lines of the listeners that run
        // behind it (the e-mail, the invoice number): the notification that repeats
        // the status comes after all of them.
        $this->changeStatus($order, OrderStatus::CODE_PAID, 'Cheque');
        $types = array_map(
            static fn (OrderHistory $line): string => $line->getEventType(),
            OrderHistoryQuery::create()->filterByOrderId($order->getId())->orderById()->find()->getData(),
        );
        self::assertSame('status_changed', $types[0]);
        self::assertNotSame('status_changed', end($types), 'Precondition: other lines follow the status line: '.implode(', ', $types));

        OrderTableMap::clearInstancePool();
        $staleOrder = OrderQuery::create()->findPk($order->getId());
        $staleOrder->setStatusId($this->statusId(OrderStatus::CODE_NOT_PAID));
        $this->changeStatus($staleOrder, OrderStatus::CODE_PAID, 'Cheque');

        self::assertSame(1, $this->linesCount($order, OrderHistoryEventType::STATUS_CHANGED));
    }

    private function changeStatus(Order $order, string $statusCode, ?string $moduleCode = null): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->statusId($statusCode));

        if (null !== $moduleCode) {
            $event->setSourceModuleCode($moduleCode);
        }

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function statusId(string $statusCode): int
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status);

        return $status->getId();
    }

    private function linesCount(Order $order, OrderHistoryEventType $type): int
    {
        return OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType($type->value)
            ->count();
    }
}
