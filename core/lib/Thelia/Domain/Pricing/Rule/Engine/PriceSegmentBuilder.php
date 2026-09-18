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

use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;

/**
 * Cuts time into the periods over which the price of a sale element does not
 * change, and prices each of them.
 *
 * Every start and end date of the rules covering the sale element is a boundary;
 * between two boundaries the set of running rules is constant, so the chain is
 * evaluated once per period. Adjacent periods that come out with the same price
 * are merged, periods where no rule runs are left out - the catalog price stands
 * there - and periods already over are dropped when the caller says what time it
 * is. What remains is what `catalog_price_rule_price` stores: a reader picks the
 * period the current instant falls in, and an opening or a closing takes effect at
 * the second without anything having to run.
 */
final class PriceSegmentBuilder
{
    public function __construct(private readonly PriceChainEvaluator $evaluator = new PriceChainEvaluator())
    {
    }

    /**
     * @param list<RuleEffect> $effects the turned-on rules covering the sale element, whatever their dates
     *
     * @return list<PriceSegment> in chronological order
     */
    public function build(
        array $effects,
        float $untaxedBasePrice,
        TaxCalculatorInterface $taxCalculator,
        ?\DateTimeInterface $now = null,
    ): array {
        if ([] === $effects) {
            return [];
        }

        $boundaries = $this->boundariesOf($effects);
        $segments = [];
        $from = null;

        foreach ([...$boundaries, null] as $until) {
            $running = array_values(array_filter(
                $effects,
                static fn (RuleEffect $effect): bool => self::covers($effect, $from, $until),
            ));

            $result = $this->evaluator->evaluate($running, $untaxedBasePrice, $taxCalculator);

            if (null !== $result) {
                $segments[] = new PriceSegment($from, $until, $result);
            }

            $from = $until;
        }

        $segments = $this->merge($segments);

        if (null === $now) {
            return $segments;
        }

        return array_values(array_filter(
            $segments,
            static fn (PriceSegment $segment): bool => !$segment->isOverAt($now),
        ));
    }

    /**
     * Whether the effect runs over the whole period. The period bounds are rule
     * dates themselves, so a rule either runs over all of it or over none of it.
     */
    private static function covers(RuleEffect $effect, ?\DateTimeInterface $from, ?\DateTimeInterface $until): bool
    {
        if (null !== $effect->startDate && (null === $from || $effect->startDate > $from)) {
            return false;
        }

        return null === $effect->endDate || (null !== $until && $effect->endDate >= $until);
    }

    /**
     * @param list<RuleEffect> $effects
     *
     * @return list<\DateTimeInterface> distinct, ascending
     */
    private function boundariesOf(array $effects): array
    {
        $byTimestamp = [];

        foreach ($effects as $effect) {
            foreach ([$effect->startDate, $effect->endDate] as $date) {
                if (null !== $date) {
                    $byTimestamp[$date->getTimestamp()] ??= \DateTimeImmutable::createFromInterface($date);
                }
            }
        }

        ksort($byTimestamp);

        return array_values($byTimestamp);
    }

    /**
     * @param list<PriceSegment> $segments
     *
     * @return list<PriceSegment>
     */
    private function merge(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $previous = [] === $merged ? null : $merged[\count($merged) - 1];

            if (null !== $previous
                && null !== $previous->validUntil
                && null !== $segment->validFrom
                && $previous->validUntil->getTimestamp() === $segment->validFrom->getTimestamp()
                && $previous->result->sameAs($segment->result)) {
                $merged[\count($merged) - 1] = new PriceSegment($previous->validFrom, $segment->validUntil, $previous->result);

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
    }
}
