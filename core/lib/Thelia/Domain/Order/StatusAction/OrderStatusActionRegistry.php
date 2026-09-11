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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The action types installed on this shop, by type identifier.
 */
final class OrderStatusActionRegistry
{
    /** @var array<string, OrderStatusActionInterface> */
    private array $actions = [];

    /**
     * @param iterable<OrderStatusActionInterface> $actions the services tagged "thelia.order_status_action"
     */
    public function __construct(
        #[AutowireIterator('thelia.order_status_action')]
        iterable $actions,
    ) {
        foreach ($actions as $action) {
            $this->actions[$action::getType()] = $action;
        }
    }

    public function get(string $type): ?OrderStatusActionInterface
    {
        return $this->actions[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->actions[$type]);
    }

    /**
     * @return array<string, OrderStatusActionInterface> indexed by type
     */
    public function all(): array
    {
        return $this->actions;
    }
}
