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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;
use Thelia\Domain\Pricing\Rule\Engine\PriceSegment;
use Thelia\Domain\Pricing\Rule\Engine\PriceSegmentBuilder;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Writes the price the public rules give each sale element, per currency, as dated
 * segments into `catalog_price_rule_price`.
 *
 * Only the rules open to everyone are stored: their price is the same for every
 * visitor, so it can be computed once and read by everyone. A rule reserved for
 * named customers is resolved when the customer reads, never stored.
 *
 * The table is a derivative of the rules and the catalog and nothing else: every
 * write here first deletes what the batch had and rebuilds it, so the writer can be
 * run on any batch, at any time, as many times as needed, and the recompute
 * command can rebuild the whole table from scratch.
 */
class PublicPriceSegmentWriter
{
    public const TABLE = 'catalog_price_rule_price';

    private const INSERT_CHUNK = 500;

    public function __construct(
        private readonly ScopeReader $scopeReader,
        private readonly BasePriceLoader $basePriceLoader,
        private readonly TaxCalculatorLoader $taxCalculatorLoader,
        private readonly RuleEffectFactory $ruleEffectFactory,
        private readonly PriceSegmentBuilder $segmentBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Rebuilds the stored prices of the given sale elements, in every currency.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return int the number of segments written
     */
    public function recomputeForProductSaleElements(array $productSaleElementsIds, ?\DateTimeInterface $now = null): int
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return 0;
        }

        $now ??= new \DateTimeImmutable();
        $con = Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $this->deletePricesOf($productSaleElementsIds, $con);
            $rows = $this->segmentRowsFor($productSaleElementsIds, $now);
            $this->insertRows($rows, $now, $con);
            $con->commit();
        } catch (\Throwable $throwable) {
            $con->rollBack();

            throw $throwable;
        }

        return \count($rows);
    }

    /**
     * Rebuilds the stored prices of everything one rule covers.
     */
    public function recomputeRule(int $ruleId, ?\DateTimeInterface $now = null): int
    {
        return $this->recomputeForProductSaleElements($this->scopeReader->productSaleElementsOf($ruleId), $now);
    }

    /**
     * Rebuilds the whole table: every sale element any rule covers, plus every sale
     * element still carrying a stored price, in chunks.
     */
    public function recomputeAll(int $chunkSize = self::INSERT_CHUNK, ?\DateTimeInterface $now = null): int
    {
        $ids = array_values(array_unique([
            ...$this->scopeReader->allCoveredProductSaleElements(),
            ...$this->pricedProductSaleElements(),
        ]));

        $written = 0;

        foreach (array_chunk($ids, max(1, $chunkSize)) as $chunk) {
            $written += $this->recomputeForProductSaleElements($chunk, $now);
        }

        return $written;
    }

    /**
     * Drops the segments already over: they are never read again, and they would
     * otherwise pile up under every rule that ended.
     */
    public function purgeExpired(?\DateTimeInterface $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $statement = Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME)
            ->prepare(\sprintf('DELETE FROM `%s` WHERE valid_until IS NOT NULL AND valid_until <= :now', self::TABLE));
        $statement->bindValue(':now', $now->format('Y-m-d H:i:s'));
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return list<array{pse_id: int, currency_id: int, segment: PriceSegment}>
     */
    private function segmentRowsFor(array $productSaleElementsIds, \DateTimeInterface $now): array
    {
        $rules = [];

        foreach (CatalogPriceRuleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode(CatalogPriceRule::AUDIENCE_MODE_PUBLIC)
            ->find() as $rule) {
            $rules[(int) $rule->getId()] = $rule;
        }

        if ([] === $rules) {
            return [];
        }

        $covering = $this->scopeReader->coveringRules($productSaleElementsIds, array_keys($rules));

        if ([] === $covering) {
            return [];
        }

        $basePrices = $this->basePriceLoader->load(array_keys($covering));
        $taxCalculators = $this->taxCalculatorLoader->forProducts(array_map(
            static fn (BasePrices $prices): int => $prices->productId,
            $basePrices,
        ));

        $rows = [];

        // The currencies the shop offers, not the whole table a fresh install seeds:
        // a price nobody can browse in is a row nobody reads.
        foreach (CurrencyQuery::create()->filterByVisible(1)->find() as $currency) {
            $currencyId = (int) $currency->getId();
            $effectsByRuleId = $this->ruleEffectFactory->effectsIn($rules, $currency);

            if ([] === $effectsByRuleId) {
                continue;
            }

            foreach ($covering as $pseId => $ruleIds) {
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

                foreach ($this->segmentBuilder->build($effects, $base, $taxCalculator, $now) as $segment) {
                    if ($segment->result->wasClamped()) {
                        $this->logger->warning('A catalog price rule took a price below zero; the price was floored at zero.', [
                            'product_sale_elements_id' => $pseId,
                            'currency_id' => $currencyId,
                            'rule_ids' => $segment->result->clampedRuleIds,
                        ]);
                    }

                    $rows[] = ['pse_id' => $pseId, 'currency_id' => $currencyId, 'segment' => $segment];
                }
            }
        }

        return $rows;
    }

    /**
     * @param list<array{pse_id: int, currency_id: int, segment: PriceSegment}> $rows
     */
    private function insertRows(array $rows, \DateTimeInterface $now, ConnectionInterface $con): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $index => $row) {
                $segment = $row['segment'];
                $placeholders[] = \sprintf('(:pse%1$d, :currency%1$d, :from%1$d, :until%1$d, :price%1$d, :rule%1$d, :display%1$d, :computed%1$d)', $index);
                $values[':pse'.$index] = $row['pse_id'];
                $values[':currency'.$index] = $row['currency_id'];
                $values[':from'.$index] = $segment->validFrom?->format('Y-m-d H:i:s');
                $values[':until'.$index] = $segment->validUntil?->format('Y-m-d H:i:s');
                $values[':price'.$index] = number_format($segment->result->untaxedPrice, 6, '.', '');
                $values[':rule'.$index] = $segment->result->ruleId;
                $values[':display'.$index] = $segment->result->displayInitialPrice ? 1 : 0;
                $values[':computed'.$index] = $now->format('Y-m-d H:i:s');
            }

            $statement = $con->prepare(\sprintf(
                'INSERT INTO `%s` (product_sale_elements_id, currency_id, valid_from, valid_until, price, catalog_price_rule_id, display_initial_price, computed_at) VALUES %s',
                self::TABLE,
                implode(', ', $placeholders),
            ));

            foreach ($values as $name => $value) {
                $statement->bindValue($name, $value, null === $value ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            }

            $statement->execute();
        }
    }

    /**
     * @param list<int> $productSaleElementsIds
     */
    private function deletePricesOf(array $productSaleElementsIds, ConnectionInterface $con): void
    {
        $con->exec(\sprintf(
            'DELETE FROM `%s` WHERE product_sale_elements_id IN (%s)',
            self::TABLE,
            implode(', ', $productSaleElementsIds),
        ));
    }

    /**
     * @return list<int>
     */
    private function pricedProductSaleElements(): array
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)
            ->query(\sprintf('SELECT DISTINCT product_sale_elements_id FROM `%s`', self::TABLE));

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }
}
