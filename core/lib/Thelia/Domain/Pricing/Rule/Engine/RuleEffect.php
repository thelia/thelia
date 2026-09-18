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
 * One rule as the engine sees it for one currency: what it does, in which order it
 * comes, and when it runs. The scope is not here - whoever hands a list of effects
 * to the engine has already kept the ones covering the sale element at hand.
 */
#[Exclude]
final readonly class RuleEffect
{
    /**
     * @param float $value the percentage taken off, or the amount off or fixed price in the currency, tax included
     */
    public function __construct(
        public int $ruleId,
        public int $priority,
        public bool $stopProcessing,
        public EffectType $type,
        public float $value,
        public bool $displayInitialPrice,
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,
    ) {
    }

    /**
     * Whether the rule prices at the given instant. A missing bound is an open one,
     * and the end is excluded: a rule ending at midnight has stopped at midnight.
     */
    public function isRunningAt(\DateTimeInterface $now): bool
    {
        if (null !== $this->startDate && $this->startDate > $now) {
            return false;
        }

        return null === $this->endDate || $this->endDate > $now;
    }

    /**
     * The order two rules covering the same sale element apply in: by ascending
     * priority, then by ascending id, so two rules of the same priority always come
     * in the same, announced order rather than in the one the database returned.
     */
    public static function compare(self $left, self $right): int
    {
        return [$left->priority, $left->ruleId] <=> [$right->priority, $right->ruleId];
    }
}
