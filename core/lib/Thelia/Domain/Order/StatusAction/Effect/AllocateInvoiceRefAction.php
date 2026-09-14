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
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;

/**
 * Gives the order its invoice number, unless it already has one. Runs whether
 * or not the automatic numbering setting is on: an administrator who attaches
 * it to a status asked for it explicitly.
 */
final readonly class AllocateInvoiceRefAction implements OrderStatusActionInterface
{
    public function __construct(
        private InvoiceRefAllocator $invoiceRefAllocator,
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
    }
}
