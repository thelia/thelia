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

namespace Thelia\Domain\Pricing\Rule\Preview;

use Propel\Runtime\Propel;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Domain\Pricing\Rule\Engine\EffectType;
use Thelia\Domain\Pricing\Rule\Engine\PriceChainEvaluator;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\BasePrices;
use Thelia\Domain\Pricing\Rule\Storage\RuleEffectFactory;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Domain\Pricing\Rule\Storage\TaxCalculatorLoader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Shows a merchant what a rule would do before it is stored or turned on: how many
 * products it covers, and the price before and after on a sample of them.
 *
 * The definition is priced as if it were running now, chained with the public
 * rules already running - the rule being edited excepted, since the definition
 * replaces it. Nothing is written: the criteria are compiled to the same predicate
 * the materializer would write, and read instead.
 */
class RulePreviewService
{
    public const DEFAULT_SAMPLE_SIZE = 10;

    public function __construct(
        private readonly ScopeMaterializer $scopeMaterializer,
        private readonly ScopeReader $scopeReader,
        private readonly BasePriceLoader $basePriceLoader,
        private readonly TaxCalculatorLoader $taxCalculatorLoader,
        private readonly RuleEffectFactory $ruleEffectFactory,
        private readonly PriceChainEvaluator $chainEvaluator,
    ) {
    }

    /**
     * @param int|null $editedRuleId the rule the definition will replace, left out of the chain
     */
    public function preview(
        CatalogPriceRuleCreateEvent $definition,
        Currency $currency,
        int $sampleSize = self::DEFAULT_SAMPLE_SIZE,
        ?int $editedRuleId = null,
        ?\DateTimeInterface $now = null,
    ): RulePreview {
        $now ??= new \DateTimeImmutable();
        $draft = $this->draftOf($definition, $editedRuleId);
        [$predicate, $unknown] = $this->scopeMaterializer->predicateFor($draft, $definition->getCriteria());

        $con = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        $counts = $con->query(\sprintf(
            'SELECT COUNT(DISTINCT p.id) AS products, COUNT(DISTINCT pse.id) AS sale_elements
             FROM `product_sale_elements` pse
             INNER JOIN `product` p ON p.id = pse.product_id
             WHERE %s',
            $predicate,
        ))->fetch(\PDO::FETCH_ASSOC) ?: ['products' => 0, 'sale_elements' => 0];

        $sample = $con->query(\sprintf(
            'SELECT pse.id
             FROM `product_sale_elements` pse
             INNER JOIN `product` p ON p.id = pse.product_id
             WHERE %s
             ORDER BY pse.product_id ASC, pse.is_default DESC, pse.id ASC
             LIMIT %d',
            $predicate,
            max(1, $sampleSize),
        ))->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        return new RulePreview(
            (int) $counts['products'],
            (int) $counts['sale_elements'],
            $this->linesFor(array_map('intval', $sample), $draft, $definition, $currency, $editedRuleId, $now),
            $unknown,
        );
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return list<PreviewLine>
     */
    private function linesFor(
        array $productSaleElementsIds,
        CatalogPriceRule $draft,
        CatalogPriceRuleCreateEvent $definition,
        Currency $currency,
        ?int $editedRuleId,
        \DateTimeInterface $now,
    ): array {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $basePrices = $this->basePriceLoader->load($productSaleElementsIds);
        $taxCalculators = $this->taxCalculatorLoader->forProducts(array_map(
            static fn (BasePrices $prices): int => $prices->productId,
            $basePrices,
        ));

        $runningRules = [];

        foreach (CatalogPriceRuleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode(CatalogPriceRule::AUDIENCE_MODE_PUBLIC)
            ->find() as $rule) {
            if ((int) $rule->getId() !== $editedRuleId && $rule->isRunningAt($now)) {
                $runningRules[(int) $rule->getId()] = $rule;
            }
        }

        $effectsByRuleId = $this->ruleEffectFactory->effectsIn($runningRules, $currency);
        $covering = $this->scopeReader->coveringRules($productSaleElementsIds, array_keys($runningRules));
        $draftEffect = $this->draftEffect($draft, $definition, $currency);
        $references = $this->referencesOf($productSaleElementsIds);
        $currencyId = (int) $currency->getId();
        $lines = [];

        foreach ($productSaleElementsIds as $pseId) {
            $prices = $basePrices[$pseId] ?? null;
            $base = $prices?->in($currencyId);
            $taxCalculator = null === $prices ? null : ($taxCalculators[$prices->productId] ?? null);

            if (null === $base || null === $taxCalculator) {
                continue;
            }

            $effects = array_values(array_filter(array_map(
                static fn (int $ruleId): ?RuleEffect => $effectsByRuleId[$ruleId] ?? null,
                $covering[$pseId] ?? [],
            )));

            $before = $this->chainEvaluator->evaluate($effects, $base, $taxCalculator)?->untaxedPrice ?? $base;

            if (null !== $draftEffect) {
                $effects[] = $draftEffect;
            }

            $afterResult = $this->chainEvaluator->evaluate($effects, $base, $taxCalculator);
            $after = $afterResult?->untaxedPrice ?? $base;

            $lines[] = new PreviewLine(
                $pseId,
                $prices->productId,
                $references[$pseId] ?? '',
                $before,
                (float) $taxCalculator->getTaxedPrice($before),
                $after,
                (float) $taxCalculator->getTaxedPrice($after),
                null !== $afterResult && null !== $draftEffect && \in_array($draftEffect->ruleId, $afterResult->appliedRuleIds, true),
            );
        }

        return $lines;
    }

    /**
     * A model carrying the definition, never saved: what the materializer and the
     * effect factory read a rule through.
     */
    private function draftOf(CatalogPriceRuleCreateEvent $definition, ?int $editedRuleId): CatalogPriceRule
    {
        $draft = new CatalogPriceRule();
        $draft
            ->setId($editedRuleId ?? 0)
            ->setActive(true)
            ->setPriority($definition->getPriority())
            ->setStopProcessing($definition->isStopProcessing())
            ->setEffectType($definition->getEffectType())
            ->setPercentageValue(null === $definition->getPercentageValue() ? null : (string) $definition->getPercentageValue())
            ->setAudienceMode($definition->getAudienceMode())
            ->setDisplayInitialPrice($definition->isDisplayInitialPrice())
            ->setIncludeSubcategories($definition->isIncludeSubcategories());

        return $draft;
    }

    private function draftEffect(CatalogPriceRule $draft, CatalogPriceRuleCreateEvent $definition, Currency $currency): ?RuleEffect
    {
        $type = EffectType::tryFrom($definition->getEffectType());

        if (null === $type) {
            return null;
        }

        $value = $type->isPerCurrency()
            ? ($definition->getEffectValuesByCurrency()[(int) $currency->getId()] ?? null)
            : $definition->getPercentageValue();

        if (null === $value) {
            return null;
        }

        return new RuleEffect(
            (int) $draft->getId(),
            $definition->getPriority(),
            $definition->isStopProcessing(),
            $type,
            $value,
            $definition->isDisplayInitialPrice(),
        );
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, string>
     */
    private function referencesOf(array $productSaleElementsIds): array
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT id, ref FROM `product_sale_elements` WHERE id IN (%s)',
            implode(', ', $productSaleElementsIds),
        ));

        $references = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $references[(int) $row['id']] = (string) $row['ref'];
        }

        return $references;
    }
}
