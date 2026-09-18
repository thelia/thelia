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

namespace Thelia\Domain\Pricing\Rule\Storage;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Pricing\Rule\Engine\EffectType;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleEffectCurrencyQuery;
use Thelia\Model\Currency;

/**
 * Reads rules into what the engine works on, for one currency.
 *
 * A percentage is the same in every currency; an amount or a fixed price is typed
 * per currency, and a rule with no value in the currency being priced does not
 * take part there - nothing is converted on the merchant's behalf.
 */
class RuleEffectFactory
{
    /**
     * @param iterable<CatalogPriceRule> $rules
     *
     * @return array<int, RuleEffect> keyed by rule id; a rule with no value in the currency is absent
     */
    public function effectsIn(iterable $rules, Currency $currency): array
    {
        $rules = $rules instanceof \Traversable ? iterator_to_array($rules, false) : $rules;

        if ([] === $rules) {
            return [];
        }

        $ruleIds = array_map(static fn (CatalogPriceRule $rule): int => (int) $rule->getId(), $rules);

        /** @var array<int, float> $valuesByRuleId */
        $valuesByRuleId = [];

        foreach (CatalogPriceRuleEffectCurrencyQuery::create()
            ->filterByCatalogPriceRuleId($ruleIds, Criteria::IN)
            ->filterByCurrencyId($currency->getId())
            ->find() as $effectCurrency) {
            $valuesByRuleId[(int) $effectCurrency->getCatalogPriceRuleId()] = (float) $effectCurrency->getValue();
        }

        $effects = [];

        foreach ($rules as $rule) {
            $type = EffectType::tryFrom((int) $rule->getEffectType());

            if (null === $type) {
                continue;
            }

            $value = $type->isPerCurrency()
                ? ($valuesByRuleId[(int) $rule->getId()] ?? null)
                : (null === $rule->getPercentageValue() ? null : (float) $rule->getPercentageValue());

            if (null === $value) {
                continue;
            }

            $effects[(int) $rule->getId()] = new RuleEffect(
                (int) $rule->getId(),
                (int) $rule->getPriority(),
                (bool) $rule->getStopProcessing(),
                $type,
                $value,
                (bool) $rule->getDisplayInitialPrice(),
                $rule->getStartDate(),
                $rule->getEndDate(),
            );
        }

        return $effects;
    }
}
