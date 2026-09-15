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

namespace Thelia\Domain\OrderReturn\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\OrderReturn;

/**
 * Writes what happens to a return in the history of the order it came from.
 *
 * A return is a second story told about an order, and the merchant reads only one
 * timeline. Opening a return, moving it between statuses and pointing its reception
 * are the three gestures that change what the customer is owed, so each of them
 * leaves a line on the order.
 *
 * Every handler runs at priority 64, after Thelia\Action\OrderReturn (128) has
 * committed its own transaction. This matters more here than on an order: each of
 * the three actions wraps its writes in an explicit transaction it rolls back on any
 * failure, and a history row inserted inside that transaction would be rolled back
 * with the gesture it was meant to record — or worse, survive a gesture that failed,
 * had it been written before. At 64 the return is written, its reference is
 * allocated, and the status on it is the one that was actually reached.
 *
 * Nothing but codes and references goes into a payload. A return carries a refusal
 * reason and a per-line condition the merchant types in free text; those are the
 * merchant's words about a customer, and the order history is read by everyone who
 * can open the order sheet. They stay on the return, where they were written.
 */
readonly class RecordOrderReturnHistoryListener
{
    /**
     * The status each in-flight return status change started from.
     *
     * The "from" code only exists before the action runs, and the handler that writes
     * the line runs after. A WeakMap keyed by the event object — rather than a plain
     * property — keeps two concurrent dispatches in the same process from reading each
     * other's value, and lets an abandoned entry go with its event when a listener in
     * between stops the propagation and the handler at 64 never runs.
     *
     * @var \WeakMap<OrderReturnEvent, string|null>
     */
    private \WeakMap $statusCodeBeforeChange;

    public function __construct(
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
        $this->statusCodeBeforeChange = new \WeakMap();
    }

    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_CREATE, priority: 64)]
    public function onReturnOpened(OrderReturnEvent $event): void
    {
        $return = $event->getOrderReturn();
        $orderId = $return->getOrderId();

        if (null === $orderId) {
            return;
        }

        $this->orderHistoryRecorder->record(
            $orderId,
            OrderHistoryEventType::RETURN_OPENED->value,
            $this->referencePayload($return),
        );
    }

    /**
     * Runs at 192, ahead of Thelia\Action\OrderReturn::updateStatus (128), purely to
     * read the status the return still has. Writes nothing.
     *
     * What is read here is the status carried by the return the caller handed over.
     * The action rereads the row under a lock before deciding, so a return whose
     * status changed in another process between this read and that lock would be
     * recorded as coming from the status the caller believed in. The transition
     * itself is still the one the lock authorized: only the "from" of the line can
     * be that stale, and only under a concurrency the state machine already refuses
     * to act on twice.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_UPDATE_STATUS, priority: 192)]
    public function rememberStatusBeforeChange(OrderReturnEvent $event): void
    {
        $this->statusCodeBeforeChange[$event] = $event->getOrderReturn()->getStatusCode();
    }

    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_UPDATE_STATUS, priority: 64)]
    public function onReturnStatusChanged(OrderReturnEvent $event): void
    {
        $return = $event->getOrderReturn();
        $orderId = $return->getOrderId();
        $toStatusCode = $return->getStatusCode();

        $fromStatusCode = $this->statusCodeBeforeChange[$event] ?? null;
        unset($this->statusCodeBeforeChange[$event]);

        if (null === $orderId || null === $toStatusCode) {
            return;
        }

        // A transition onto the status the return already has is not a change. The
        // recorder drops a repeated identical entry on its own, but only when it is
        // the latest of its kind: a return that went back and forth would otherwise
        // get a line saying it moved from a status to itself.
        if ($fromStatusCode === $toStatusCode) {
            return;
        }

        $this->orderHistoryRecorder->record(
            $orderId,
            OrderHistoryEventType::RETURN_STATUS_CHANGED->value,
            $this->referencePayload($return) + ['from' => $fromStatusCode, 'to' => $toStatusCode],
        );
    }

    /**
     * Pointing a reception is recorded as a reception, not as the status change it
     * also performs: ORDER_RETURN_RECEIVE moves the return to "received" itself,
     * without going through ORDER_RETURN_UPDATE_STATUS, and what the merchant did
     * was receive the goods.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_RECEIVE, priority: 64)]
    public function onReturnReceived(OrderReturnEvent $event): void
    {
        $return = $event->getOrderReturn();
        $orderId = $return->getOrderId();

        if (null === $orderId) {
            return;
        }

        $this->orderHistoryRecorder->record(
            $orderId,
            OrderHistoryEventType::RETURN_RECEIVED->value,
            $this->referencePayload($return),
        );
    }

    /**
     * The reference of the return, which is what tells two returns of the same order
     * apart — both for the merchant reading the line and for the deduplication the
     * recorder applies on an identical payload.
     *
     * @return array<string, string>
     */
    private function referencePayload(OrderReturn $return): array
    {
        $reference = $return->getRef();

        return null === $reference || '' === $reference ? [] : ['return_ref' => $reference];
    }
}
