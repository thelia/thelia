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

namespace Thelia\Domain\Pricing\Rule\Overview;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * The rules as the back-office list shows them, with the counts a screen needs -
 * read for the whole list in a fixed number of statements, never one per row.
 */
class CatalogPriceRuleOverviewQuery
{
    public function __construct(private readonly ScopeMaterializer $scopeMaterializer)
    {
    }

    /**
     * @param list<int>|null $ruleIds every rule when null
     * @param string|null    $locale  when given, the translations are joined so a screen reading the titles costs no query per row
     *
     * @return list<CatalogPriceRuleOverview> keyed by position, in ascending priority then id
     */
    public function overview(?array $ruleIds = null, ?\DateTimeInterface $now = null, ?string $locale = null): array
    {
        $now ??= new \DateTimeImmutable();
        $query = CatalogPriceRuleQuery::create()->orderByPriority()->orderById();

        if (null !== $locale) {
            $query->joinWithI18n($locale, Criteria::LEFT_JOIN);
        }

        if (null !== $ruleIds) {
            $query->filterById([] === $ruleIds ? [-1] : $ruleIds, Criteria::IN);
        }

        $rules = iterator_to_array($query->find(), false);

        if ([] === $rules) {
            return [];
        }

        $ids = array_map(static fn (CatalogPriceRule $rule): int => (int) $rule->getId(), $rules);
        $coverage = $this->coverageOf($ids);
        $customers = $this->customerCountsOf($ids);
        $unknown = $this->unknownCriterionTypesOf($ids);

        $overview = [];

        foreach ($rules as $rule) {
            $id = (int) $rule->getId();
            $overview[] = new CatalogPriceRuleOverview(
                $rule,
                $rule->getStateAt($now),
                $coverage[$id]['products'] ?? 0,
                $coverage[$id]['sale_elements'] ?? 0,
                $customers[$id] ?? 0,
                $unknown[$id] ?? [],
            );
        }

        return $overview;
    }

    public function forRule(int $ruleId, ?\DateTimeInterface $now = null): ?CatalogPriceRuleOverview
    {
        return $this->overview([$ruleId], $now)[0] ?? null;
    }

    /**
     * @param list<int> $ruleIds
     *
     * @return array<int, array{products: int, sale_elements: int}>
     */
    private function coverageOf(array $ruleIds): array
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT scope.catalog_price_rule_id AS rule_id, COUNT(DISTINCT pse.product_id) AS products, COUNT(*) AS sale_elements
             FROM `%s` scope
             INNER JOIN `product_sale_elements` pse ON pse.id = scope.product_sale_elements_id
             WHERE scope.catalog_price_rule_id IN (%s)
             GROUP BY scope.catalog_price_rule_id',
            ScopeMaterializer::TABLE,
            implode(', ', $ruleIds),
        ));

        $coverage = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $coverage[(int) $row['rule_id']] = ['products' => (int) $row['products'], 'sale_elements' => (int) $row['sale_elements']];
        }

        return $coverage;
    }

    /**
     * @param list<int> $ruleIds
     *
     * @return array<int, int>
     */
    private function customerCountsOf(array $ruleIds): array
    {
        $counts = [];

        foreach (CatalogPriceRuleCustomerQuery::create()
            ->filterByCatalogPriceRuleId($ruleIds, Criteria::IN)
            ->withColumn('COUNT(*)', 'customers')
            ->select(['CatalogPriceRuleId', 'customers'])
            ->groupByCatalogPriceRuleId()
            ->find() as $row) {
            $counts[(int) $row['CatalogPriceRuleId']] = (int) $row['customers'];
        }

        return $counts;
    }

    /**
     * @param list<int> $ruleIds
     *
     * @return array<int, list<string>>
     */
    private function unknownCriterionTypesOf(array $ruleIds): array
    {
        $known = $this->scopeMaterializer->knownCriterionTypes();
        $unknown = [];

        foreach (CatalogPriceRuleCriterionQuery::create()
            ->filterByCatalogPriceRuleId($ruleIds, Criteria::IN)
            ->select(['CatalogPriceRuleId', 'Type'])
            ->distinct()
            ->find() as $row) {
            if (!\in_array($row['Type'], $known, true)) {
                $unknown[(int) $row['CatalogPriceRuleId']][] = (string) $row['Type'];
            }
        }

        return $unknown;
    }
}
