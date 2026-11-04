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

use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;

/**
 * The stock operation a status change applies, and the stock action that chose it
 * (null when the fixed rule of the core, or a module, chose it).
 */
final readonly class StockOperationDecision
{
    public function __construct(
        public int $operation,
        public ?int $actionId,
    ) {
    }

    public function operationName(): string
    {
        return match ($this->operation) {
            GetStockUpdateOperationOnOrderStatusChangeEvent::INCREASE_STOCK => 'increase',
            GetStockUpdateOperationOnOrderStatusChangeEvent::DECREASE_STOCK => 'decrease',
            default => 'none',
        };
    }

    public function movesStock(): bool
    {
        return GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING !== $this->operation;
    }
}
