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
 *
 * The rule operation is what the fixed rule of the core, and the modules listening before
 * the stock actions, had decided before the stock actions were read: null when unknown.
 */
final readonly class StockOperationDecision
{
    public function __construct(
        public int $operation,
        public ?int $actionId,
        public ?int $ruleOperation = null,
    ) {
    }

    public function operationName(): string
    {
        return self::nameOf($this->operation);
    }

    public function ruleOperationName(): ?string
    {
        return null === $this->ruleOperation ? null : self::nameOf($this->ruleOperation);
    }

    public function movesStock(): bool
    {
        return GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING !== $this->operation;
    }

    /**
     * Whether the stock moved otherwise than the fixed rule would have moved it.
     */
    public function departsFromTheRule(): bool
    {
        return null !== $this->ruleOperation && $this->ruleOperation !== $this->operation;
    }

    public static function nameOf(int $operation): string
    {
        return match ($operation) {
            GetStockUpdateOperationOnOrderStatusChangeEvent::INCREASE_STOCK => 'increase',
            GetStockUpdateOperationOnOrderStatusChangeEvent::DECREASE_STOCK => 'decrease',
            default => 'none',
        };
    }
}
