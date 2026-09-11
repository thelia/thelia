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

namespace Thelia\Domain\Order\StatusAction;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;

/**
 * An effect the shop can attach to an order status change from the back office.
 *
 * The core ships a few (e-mails, stock, invoice number, coupons); a module adds
 * its own by implementing this interface, and the type shows up in the back
 * office list at once. Implementations are collected through the
 * "thelia.order_status_action" tag (autoconfigured).
 *
 * The payload is typed by an administrator and run by the server: an
 * implementation validates it field by field and never evaluates it.
 */
#[AutoconfigureTag('thelia.order_status_action')]
interface OrderStatusActionInterface
{
    /**
     * The identifier stored in order_status_action.action_type, stable across versions.
     */
    public static function getType(): string;

    /**
     * The fields an administrator fills in to configure this action.
     *
     * @return list<OrderStatusActionPayloadField>
     */
    public function describePayload(): array;

    /**
     * Checks a payload and returns it with only the declared fields, normalized.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws InvalidOrderStatusActionPayloadException
     */
    public function normalizePayload(array $payload): array;

    public function execute(OrderStatusActionContext $context): void;
}
