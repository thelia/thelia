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
use Thelia\Domain\Pricing\ResolvedCatalogPrice;
use Thelia\Model\Currency;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Reads the stored public price of a batch of sale elements at one instant: the
 * segment the instant falls in, one statement for the whole batch.
 */
class PublicPriceReader
{
    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ResolvedCatalogPrice> keyed by sale element id
     */
    public function currentPrices(array $productSaleElementsIds, Currency $currency, ?\DateTimeInterface $now = null): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $now ??= new \DateTimeImmutable();

        $statement = Propel::getConnection(CatalogPriceRuleTableMap::DATABASE_NAME)->prepare(\sprintf(
            'SELECT product_sale_elements_id, price, catalog_price_rule_id, display_initial_price, valid_until
             FROM `%s`
             WHERE product_sale_elements_id IN (%s)
               AND currency_id = :currencyId
               AND (valid_from IS NULL OR valid_from <= :now)
               AND (valid_until IS NULL OR valid_until > :now)',
            PublicPriceSegmentWriter::TABLE,
            implode(', ', $productSaleElementsIds),
        ));
        $statement->bindValue(':currencyId', $currency->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':now', $now->format('Y-m-d H:i:s'));
        $statement->execute();

        $prices = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $pseId = (int) $row['product_sale_elements_id'];
            $prices[$pseId] = new ResolvedCatalogPrice(
                $pseId,
                (float) $row['price'],
                (int) $row['catalog_price_rule_id'],
                (bool) $row['display_initial_price'],
                null === $row['valid_until'] ? null : new \DateTimeImmutable((string) $row['valid_until']),
            );
        }

        return $prices;
    }
}
