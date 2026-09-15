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

namespace Thelia\Domain\Order\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;

/**
 * Turns the order events the core already dispatches into history entries.
 *
 * Every recording listener runs at priority 64, after Thelia\Action\Order (128) has
 * persisted the change and committed its own transaction. Recording before that would
 * write a line for a gesture that may still fail, and the status change in particular
 * is wrapped in a transaction of its own: a history row inserted inside it would be
 * rolled back with the stock update it guards.
 *
 * The status change needs what the order looked like before, which at priority 64 is
 * already gone — the order in the event carries the new status. A second listener on
 * the same event runs at 192, ahead of the core, and keeps the previous status code
 * for the one at 64 to read back. The two are keyed by the event object itself, so
 * concurrent dispatches in the same process never read each other's value.
 */
readonly class RecordOrderHistoryListener
{
    private const ADDRESS_TYPE_DELIVERY = 'delivery';
    private const ADDRESS_TYPE_INVOICE = 'invoice';

    /**
     * The status each in-flight event started from.
     *
     * A WeakMap rather than an SplObjectStorage: nothing guarantees the listener at 64
     * ever runs — a listener in between may stop propagation — and a weak key lets the
     * abandoned entry go with the event instead of holding it for the whole process.
     *
     * @var \WeakMap<OrderEvent, string|null>
     */
    private \WeakMap $statusCodeBeforeChange;

    public function __construct(
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
        $this->statusCodeBeforeChange = new \WeakMap();
    }

    /**
     * Runs at 192, ahead of Thelia\Action\Order::updateStatus (128), purely to read the
     * status the order still has. Writes nothing.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 192)]
    public function rememberStatusBeforeChange(OrderEvent $event): void
    {
        $this->statusCodeBeforeChange[$event] = $this->statusCodeOf($event->getOrder()->getStatusId());
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 64)]
    public function onStatusUpdated(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $toStatusCode = $this->statusCodeOf($order->getStatusId());

        if (null === $toStatusCode) {
            return;
        }

        $fromStatusCode = $this->statusCodeBeforeChange[$event] ?? null;
        unset($this->statusCodeBeforeChange[$event]);

        $this->orderHistoryRecorder->recordStatusChanged(
            $order->getId(),
            $fromStatusCode,
            $toStatusCode,
            $event->getSourceModuleCode(),
        );
    }

    /**
     * The first line of the timeline, written as soon as the order exists.
     *
     * ORDER_BEFORE_PAYMENT is dispatched by Thelia\Action\Order::create once the
     * order is committed and before the payment module is called at all, which is
     * what makes it the right place: an immediate payment confirms inside that
     * call and its status change would otherwise be written first, leaving the
     * order created after it was paid.
     *
     * Priority 192 is ahead of the core listener at 128 that sends the
     * confirmation and notification emails, so the line the history opens with is
     * the creation rather than the mail announcing it.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_BEFORE_PAYMENT, priority: 192)]
    public function onOrderPlaced(OrderEvent $event): void
    {
        // The event carries the placed order as its order — it is built from it —
        // so there is no placed order set on it to read.
        $order = $event->getOrder();
        $orderId = $order->getId();

        if (null === $orderId) {
            return;
        }

        $this->orderHistoryRecorder->recordOrderCreated(
            $orderId,
            $order->getRef(),
            $event->getSourceModuleCode(),
        );
    }

    /**
     * The same line, for an order that reached ORDER_PAY without going through
     * Thelia\Action\Order::create — a module that places an order its own way, or
     * a listener that stopped propagation before the core one ran. The recorder
     * drops the second write of an identical entry, so the nominal path still
     * opens with a single creation line.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_PAY, priority: 64)]
    public function onOrderPaid(OrderEvent $event): void
    {
        $this->recordCreation($event);
    }

    #[AsEventListener(event: TheliaEvents::ORDER_CREATE_MANUAL, priority: 64)]
    public function onOrderCreatedManually(OrderManualEvent $event): void
    {
        $this->recordCreation($event);
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_DELIVERY_REF, priority: 64)]
    public function onDeliveryRefUpdated(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $this->orderHistoryRecorder->recordDeliveryRefUpdated(
            $order->getId(),
            $order->getDeliveryRef(),
            $event->getSourceModuleCode(),
        );
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_TRANSACTION_REF, priority: 64)]
    public function onTransactionRefUpdated(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $this->orderHistoryRecorder->recordTransactionRefUpdated(
            $order->getId(),
            $order->getTransactionRef(),
            $event->getSourceModuleCode(),
        );
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_ADDRESS, priority: 64)]
    public function onAddressUpdated(OrderAddressEvent $event): void
    {
        // Callers are not required to name the order on this event, and the core
        // listener does not set it either: without it there is nothing to attach the
        // entry to.
        if (!$event->hasOrder()) {
            return;
        }

        $order = $event->getOrder();
        $orderAddressId = $event->getOrderAddress()->getId();

        $this->orderHistoryRecorder->recordAddressUpdated(
            $order->getId(),
            $orderAddressId,
            $this->addressTypeOf($order, $orderAddressId),
        );
    }

    private function recordCreation(OrderEvent $event): void
    {
        if (!$event->hasPlacedOrder()) {
            return;
        }

        $placedOrder = $event->getPlacedOrder();

        $this->orderHistoryRecorder->recordOrderCreated(
            $placedOrder->getId(),
            $placedOrder->getRef(),
            $event->getSourceModuleCode(),
        );
    }

    private function addressTypeOf(Order $order, ?int $orderAddressId): ?string
    {
        return match ($orderAddressId) {
            null => null,
            $order->getDeliveryOrderAddressId() => self::ADDRESS_TYPE_DELIVERY,
            $order->getInvoiceOrderAddressId() => self::ADDRESS_TYPE_INVOICE,
            default => null,
        };
    }

    private function statusCodeOf(?int $statusId): ?string
    {
        if (null === $statusId) {
            return null;
        }

        return OrderStatusQuery::create()->findPk($statusId)?->getCode();
    }
}
