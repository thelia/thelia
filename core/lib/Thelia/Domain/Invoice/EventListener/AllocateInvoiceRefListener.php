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

namespace Thelia\Domain\Invoice\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;

/**
 * Numbers the invoice when an order becomes paid.
 *
 * Priority 64 runs after Thelia\Action\Order::updateStatus (128) has
 * persisted the new status — the same transition on which the order sets its
 * invoice_date — and before the coupon consumption listener (10).
 *
 * The listener is opt-in (invoice_ref_auto config) and never overwrites an
 * invoice_ref already set, so shops using a dedicated invoicing module keep
 * full control of the field.
 *
 * The history entry is written here rather than from a listener of its own on
 * the same event. Allocation writes the order with versioning disabled, so the
 * numbering of a legal invoice series leaves no trace anywhere else, and two
 * listeners at the same priority on the same event have no defined order
 * between them: reading the ref back from another listener would record it, or
 * not, depending on which one the container happened to register first. Called
 * from here, the entry is written once, right after the number was posed, and
 * only when this allocation is what posed it — a concurrent notification that
 * lost the race returns without a ref on its own copy of the order and records
 * nothing.
 */
readonly class AllocateInvoiceRefListener
{
    public function __construct(
        private InvoiceRefAllocator $invoiceRefAllocator,
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 64)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order->isPaid(false)) {
            return;
        }

        if (null !== $order->getInvoiceRef() && '' !== $order->getInvoiceRef()) {
            return;
        }

        if (!$this->invoiceRefAllocator->isEnabled()) {
            return;
        }

        $this->invoiceRefAllocator->allocate($order);

        $allocatedInvoiceRef = $order->getInvoiceRef();

        if (null === $allocatedInvoiceRef || '' === $allocatedInvoiceRef) {
            return;
        }

        $this->orderHistoryRecorder->recordInvoiceRefAllocated(
            $order->getId(),
            $allocatedInvoiceRef,
            $event->getSourceModuleCode(),
        );
    }
}
