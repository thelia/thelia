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

namespace Thelia\Domain\Pricing\Rule;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Pricing\CatalogPriceResolverInterface;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\ResolvedCatalogPrice;
use Thelia\Domain\Pricing\Rule\Audience\CustomerAudience;
use Thelia\Domain\Pricing\Rule\Engine\PriceChainEvaluator;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\BasePrices;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Domain\Pricing\Rule\Storage\RuleEffectFactory;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Domain\Pricing\Rule\Storage\TaxCalculatorLoader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * The core's answer to "what do the rules make of these sale elements for this
 * visitor".
 *
 * For a visitor entitled to no reserved rule - every visitor of most shops - the
 * answer is read from the stored public segments in one statement. For a customer
 * named on a rule, the sale elements that rule covers are priced on the spot: the
 * chain runs over the public rules and the customer's own, in priority order, with
 * the same engine that wrote the stored segments. The work is bounded by the sale
 * elements asked for, never by the size of the rule.
 */
class CatalogPriceRuleResolver implements CatalogPriceResolverInterface
{
    public function __construct(
        private readonly PublicPriceReader $publicPriceReader,
        private readonly PricingActivityChecker $activityChecker,
        private readonly CustomerAudience $customerAudience,
        private readonly ScopeReader $scopeReader,
        private readonly BasePriceLoader $basePriceLoader,
        private readonly TaxCalculatorLoader $taxCalculatorLoader,
        private readonly RuleEffectFactory $ruleEffectFactory,
        private readonly PriceChainEvaluator $chainEvaluator,
    ) {
    }

    public function resolve(
        array $productSaleElementsIds,
        Currency $currency,
        ?Customer $customer,
        ?\DateTimeInterface $now = null,
    ): array {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $now ??= new \DateTimeImmutable();
        $publicPrices = $this->activityChecker->hasActivePublicRule()
            ? $this->publicPriceReader->currentPrices($productSaleElementsIds, $currency, $now)
            : [];

        if (null === $customer || !$this->activityChecker->hasActiveNamedRule()) {
            return $publicPrices;
        }

        $namedRuleIds = $this->customerAudience->entitledRuleIds($customer);

        if ([] === $namedRuleIds) {
            return $publicPrices;
        }

        return array_replace($publicPrices, $this->resolveForNamedCustomer($productSaleElementsIds, $currency, $namedRuleIds, $now));
    }

    /**
     * @param list<int> $productSaleElementsIds
     * @param list<int> $namedRuleIds
     *
     * @return array<int, ResolvedCatalogPrice>
     */
    private function resolveForNamedCustomer(array $productSaleElementsIds, Currency $currency, array $namedRuleIds, \DateTimeInterface $now): array
    {
        $rules = [];

        foreach (CatalogPriceRuleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode(CatalogPriceRule::AUDIENCE_MODE_PUBLIC)
            ->_or()
            ->filterById($namedRuleIds, Criteria::IN)
            ->find() as $rule) {
            if ($rule->isRunningAt($now)) {
                $rules[(int) $rule->getId()] = $rule;
            }
        }

        $runningNamedRuleIds = array_values(array_intersect($namedRuleIds, array_keys($rules)));

        if ([] === $runningNamedRuleIds) {
            return [];
        }

        $covering = $this->scopeReader->coveringRules($productSaleElementsIds, array_keys($rules));
        $namedCovered = array_filter(
            $covering,
            static fn (array $ruleIds): bool => [] !== array_intersect($ruleIds, $runningNamedRuleIds),
        );

        if ([] === $namedCovered) {
            return [];
        }

        $basePrices = $this->basePriceLoader->load(array_keys($namedCovered));
        $taxCalculators = $this->taxCalculatorLoader->forProducts(array_map(
            static fn (BasePrices $prices): int => $prices->productId,
            $basePrices,
        ));
        $effectsByRuleId = $this->ruleEffectFactory->effectsIn($rules, $currency);
        $currencyId = (int) $currency->getId();
        $resolved = [];

        foreach ($namedCovered as $pseId => $ruleIds) {
            $prices = $basePrices[$pseId] ?? null;
            $base = $prices?->in($currencyId);
            $taxCalculator = null === $prices ? null : ($taxCalculators[$prices->productId] ?? null);

            if (null === $base || null === $taxCalculator) {
                continue;
            }

            $effects = array_values(array_filter(array_map(
                static fn (int $ruleId): ?RuleEffect => $effectsByRuleId[$ruleId] ?? null,
                $ruleIds,
            )));

            $result = $this->chainEvaluator->evaluate($effects, $base, $taxCalculator);

            if (null === $result) {
                continue;
            }

            $resolved[$pseId] = new ResolvedCatalogPrice(
                $pseId,
                $result->untaxedPrice,
                $result->ruleId,
                $result->displayInitialPrice,
                $this->earliestEndOf($result->appliedRuleIds, $rules),
            );
        }

        return $resolved;
    }

    /**
     * @param list<int>                    $ruleIds
     * @param array<int, CatalogPriceRule> $rules
     */
    private function earliestEndOf(array $ruleIds, array $rules): ?\DateTimeInterface
    {
        $earliest = null;

        foreach ($ruleIds as $ruleId) {
            $endDate = ($rules[$ruleId] ?? null)?->getEndDate();

            if (null !== $endDate && (null === $earliest || $endDate < $earliest)) {
                $earliest = $endDate;
            }
        }

        return $earliest;
    }
}
