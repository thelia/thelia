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

namespace Thelia\Domain\Order\Edition;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The totals of the order before and after an edit, and what changed. For an order that
 * was paid, the gap between the two is money to give back or to ask for.
 */
#[Exclude]
final readonly class OrderEditOutcome
{
    /**
     * @param list<array<string, string>> $changes
     */
    public function __construct(
        public float $totalBefore,
        public float $totalAfter,
        public float $taxBefore,
        public float $taxAfter,
        public array $changes,
        public bool $wasPaid,
    ) {
    }

    public function amountToRefund(): float
    {
        return $this->wasPaid ? max(0.0, round($this->totalBefore - $this->totalAfter, 2)) : 0.0;
    }

    public function amountToCollect(): float
    {
        return $this->wasPaid ? max(0.0, round($this->totalAfter - $this->totalBefore, 2)) : 0.0;
    }
}
