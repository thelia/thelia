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

use Propel\Runtime\Propel;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Which of the given rules cover which of the given sale elements, from the
 * materialized scope, in one statement.
 */
class ScopeReader
{
    /**
     * @param list<int> $productSaleElementsIds
     * @param list<int> $ruleIds
     *
     * @return array<int, list<int>> sale element id => the ids of the rules covering it
     */
    public function coveringRules(array $productSaleElementsIds, array $ruleIds): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));
        $ruleIds = array_values(array_unique(array_map('intval', $ruleIds)));

        if ([] === $productSaleElementsIds || [] === $ruleIds) {
            return [];
        }

        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT product_sale_elements_id, catalog_price_rule_id FROM `%s` WHERE product_sale_elements_id IN (%s) AND catalog_price_rule_id IN (%s)',
            ScopeMaterializer::TABLE,
            implode(', ', $productSaleElementsIds),
            implode(', ', $ruleIds),
        ));

        $covering = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $covering[(int) $row['product_sale_elements_id']][] = (int) $row['catalog_price_rule_id'];
        }

        return $covering;
    }

    /**
     * @return list<int> every sale element the rule covers
     */
    public function productSaleElementsOf(int $ruleId): array
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT product_sale_elements_id FROM `%s` WHERE catalog_price_rule_id = %d',
            ScopeMaterializer::TABLE,
            $ruleId,
        ));

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return list<int> every sale element any rule covers
     */
    public function allCoveredProductSaleElements(): array
    {
        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT DISTINCT product_sale_elements_id FROM `%s`',
            ScopeMaterializer::TABLE,
        ));

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }
}
