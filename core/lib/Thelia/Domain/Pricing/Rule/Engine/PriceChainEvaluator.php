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
 * Runs the rules covering one sale element, in order, over its catalog price.
 *
 * The rules come by ascending priority then ascending id. Each one applies to what
 * the previous one left, and a rule that asks to stop processing is the last one
 * examined: a fixed price of priority 10 with the stop flag wins over a "10% off
 * the whole shop" of priority 100, and the two never combine.
 *
 * The caller decides which effects take part: the ones covering the sale element,
 * and - when the question is "now" - the ones running now. The evaluator applies
 * every effect it is handed.
 */
final class PriceChainEvaluator
{
    public function __construct(private readonly PriceEffectApplier $applier = new PriceEffectApplier())
    {
    }

    /**
     * @param list<RuleEffect> $effects the rules covering the sale element, in any order
     *
     * @return ChainResult|null null when no rule applies, so the catalog price stands
     */
    public function evaluate(array $effects, float $untaxedBasePrice, TaxCalculatorInterface $taxCalculator): ?ChainResult
    {
        if ([] === $effects) {
            return null;
        }

        usort($effects, RuleEffect::compare(...));

        $price = $untaxedBasePrice;
        $appliedRuleIds = [];
        $clampedRuleIds = [];
        $displayInitialPrice = false;
        $lastRuleId = null;

        foreach ($effects as $effect) {
            if ($this->applier->wouldGoNegative($price, $effect, $taxCalculator)) {
                $clampedRuleIds[] = $effect->ruleId;
            }

            $price = $this->applier->apply($price, $effect, $taxCalculator);
            $appliedRuleIds[] = $effect->ruleId;
            $displayInitialPrice = $displayInitialPrice || $effect->displayInitialPrice;
            $lastRuleId = $effect->ruleId;

            if ($effect->stopProcessing) {
                break;
            }
        }

        if (null === $lastRuleId) {
            return null;
        }

        return new ChainResult($price, $lastRuleId, $displayInitialPrice, $appliedRuleIds, $clampedRuleIds);
    }
}
