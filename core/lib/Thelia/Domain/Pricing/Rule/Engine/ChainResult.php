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

namespace Thelia\Domain\Pricing\Rule\Engine;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a chain of rules made of a price: the price itself, the rule that had the
 * last word, and whether any rule asked for the catalog price to stay visible.
 */
#[Exclude]
final readonly class ChainResult
{
    /**
     * @param list<int> $appliedRuleIds the rules that applied, in the order they did
     * @param list<int> $clampedRuleIds the rules whose effect took the price below zero, floored at zero
     */
    public function __construct(
        public float $untaxedPrice,
        public int $ruleId,
        public bool $displayInitialPrice,
        public array $appliedRuleIds,
        public array $clampedRuleIds = [],
    ) {
    }

    public function wasClamped(): bool
    {
        return [] !== $this->clampedRuleIds;
    }

    public function sameAs(self $other): bool
    {
        return $this->ruleId === $other->ruleId
            && $this->displayInitialPrice === $other->displayInitialPrice
            && abs($this->untaxedPrice - $other->untaxedPrice) < 0.0000005;
    }
}
