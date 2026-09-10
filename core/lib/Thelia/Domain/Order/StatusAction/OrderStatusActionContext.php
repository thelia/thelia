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

use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * What an action receives when it runs: the order, already holding its new
 * status, the statuses of the transition, and its own normalized payload.
 */
final readonly class OrderStatusActionContext
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public Order $order,
        public ?OrderStatus $previousStatus,
        public OrderStatus $newStatus,
        public array $payload,
    ) {
    }
}
