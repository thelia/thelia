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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;

/**
 * Gives the order its invoice number, unless it already has one. Runs whether
 * or not the automatic numbering setting is on: an administrator who attaches
 * it to a status asked for it explicitly.
 *
 * The history entry is written from here for the same reason it is written from
 * Thelia\Domain\Invoice\EventListener\AllocateInvoiceRefListener rather than from a
 * listener of its own: allocation saves the order with versioning disabled, so the
 * numbering of a legal invoice series leaves no trace anywhere else. This action is
 * the second road to a number — the one an administrator opens by hanging it on a
 * transition, which runs even with automatic numbering off — and it has to leave the
 * same line. The two roads never both write: whichever posed the number first, the
 * other finds it already there and returns.
 */
final readonly class AllocateInvoiceRefAction implements OrderStatusActionInterface
{
    public function __construct(
        private InvoiceRefAllocator $invoiceRefAllocator,
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    public static function getType(): string
    {
        return 'allocate_invoice_ref';
    }

    public function describePayload(): array
    {
        return [];
    }

    public function normalizePayload(array $payload): array
    {
        if ([] !== $payload) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(self::getType(), $payload, []);
        }

        return [];
    }

    public function execute(OrderStatusActionContext $context): void
    {
        $invoiceRef = $context->order->getInvoiceRef();

        if (null !== $invoiceRef && '' !== $invoiceRef) {
            return;
        }

        $this->invoiceRefAllocator->allocate($context->order);

        $allocatedInvoiceRef = $context->order->getInvoiceRef();

        // Only when this call is what posed the number: a concurrent allocation that
        // lost the race returns without a ref on its own copy of the order, and
        // records nothing.
        if (null === $allocatedInvoiceRef || '' === $allocatedInvoiceRef) {
            return;
        }

        $this->orderHistoryRecorder->recordInvoiceRefAllocated($context->order->getId(), $allocatedInvoiceRef);
    }
}
