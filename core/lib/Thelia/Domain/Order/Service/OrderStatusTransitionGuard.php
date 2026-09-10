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

namespace Thelia\Domain\Order\Service;

use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * Refuses the order status changes the transition graph does not allow.
 *
 * Every status change goes through Thelia\Action\Order::updateStatus(), which
 * consults this guard before writing anything, so the back office, the API, a
 * payment module confirming a payment and a scheduled command are all held to
 * the same graph. The forced mode is the escape hatch of an entitled
 * administrator; the caller is responsible for logging the override.
 */
final readonly class OrderStatusTransitionGuard
{
    public function __construct(
        private OrderStatusTransitionGraphProvider $graphProvider,
        private OrderStatusCatalog $catalog,
    ) {
    }

    public function isAllowed(int $fromStatusId, int $toStatusId): bool
    {
        return $this->graphProvider->get()->allows($fromStatusId, $toStatusId);
    }

    /**
     * @throws OrderStatusTransitionRefusedException
     */
    public function assertAllowed(Order $order, int $toStatusId, bool $forced = false): void
    {
        if ($forced || $this->isAllowed($order->getStatusId(), $toStatusId)) {
            return;
        }

        throw new OrderStatusTransitionRefusedException((string) $order->getRef(), $this->catalog->get($order->getStatusId())?->getCode() ?? (string) $order->getStatusId(), $this->catalog->get($toStatusId)?->getCode() ?? (string) $toStatusId);
    }

    /**
     * The statuses an order holding the given status may be moved to, in position
     * order. The current status is not one of them.
     *
     * @return list<OrderStatus>
     */
    public function allowedTargets(int $fromStatusId): array
    {
        $targets = [];

        foreach ($this->catalog->all() as $status) {
            if ($status->getId() !== $fromStatusId && $this->isAllowed($fromStatusId, $status->getId())) {
                $targets[] = $status;
            }
        }

        return $targets;
    }

    /**
     * Splits orders between those the graph lets reach the target status and those
     * it refuses, for a bulk change that skips the refused ones and names them.
     *
     * @param iterable<Order> $orders
     *
     * @return array{allowed: list<Order>, refused: list<Order>}
     */
    public function partition(iterable $orders, int $toStatusId): array
    {
        $partition = ['allowed' => [], 'refused' => []];

        foreach ($orders as $order) {
            $partition[$this->isAllowed($order->getStatusId(), $toStatusId) ? 'allowed' : 'refused'][] = $order;
        }

        return $partition;
    }

    /**
     * Statuses the current graph never leads to, worth a warning on the configuration screen.
     *
     * @return list<OrderStatus>
     */
    public function unreachableStatuses(): array
    {
        $statuses = $this->catalog->all();
        $unreachable = [];

        foreach ($this->graphProvider->get()->unreachableStatusIds(array_keys($statuses)) as $statusId) {
            $unreachable[] = $statuses[$statusId];
        }

        return $unreachable;
    }

    public function reset(): void
    {
        $this->graphProvider->reset();
    }
}
