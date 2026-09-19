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
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductPriceTableMap;

/**
 * Reads the catalog prices a batch of sale elements start from, in one statement.
 *
 * The currency rule is the one ProductSaleElements::getPricesByCurrency() applies:
 * a currency with no row of its own, or a row flagged `from_default_currency`, is
 * converted from the default currency at the rates. A sale element with no price in
 * the default currency has no price at all in the currencies it lacks, and is simply
 * absent there - it never stops the batch.
 */
class BasePriceLoader
{
    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, BasePrices> keyed by sale element id
     */
    public function load(array $productSaleElementsIds): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultCurrencyId = (int) $defaultCurrency->getId();
        $defaultRate = (float) $defaultCurrency->getRate();

        /** @var array<int, float> $ratesByCurrencyId */
        $ratesByCurrencyId = [];

        foreach (CurrencyQuery::create()->find() as $currency) {
            $ratesByCurrencyId[(int) $currency->getId()] = (float) $currency->getRate();
        }

        $statement = Propel::getConnection(ProductPriceTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT pse.id AS pse_id, pse.product_id AS product_id, pp.currency_id AS currency_id, pp.price AS price, pp.from_default_currency AS from_default_currency
             FROM `product_sale_elements` pse
             LEFT JOIN `product_price` pp ON pp.product_sale_elements_id = pse.id
             WHERE pse.id IN (%s)',
            implode(', ', $productSaleElementsIds),
        ));

        /** @var array<int, array{product_id: int, explicit: array<int, float>, default: float|null}> $rows */
        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $pseId = (int) $row['pse_id'];
            $rows[$pseId] ??= ['product_id' => (int) $row['product_id'], 'explicit' => [], 'default' => null];

            if (null === $row['currency_id']) {
                continue;
            }

            $currencyId = (int) $row['currency_id'];

            if ($currencyId === $defaultCurrencyId) {
                $rows[$pseId]['default'] = (float) $row['price'];
            }

            if (!$row['from_default_currency']) {
                $rows[$pseId]['explicit'][$currencyId] = (float) $row['price'];
            }
        }

        $prices = [];

        foreach ($rows as $pseId => $row) {
            $byCurrency = [];

            foreach ($ratesByCurrencyId as $currencyId => $rate) {
                if (isset($row['explicit'][$currencyId])) {
                    $byCurrency[$currencyId] = $row['explicit'][$currencyId];
                } elseif (null !== $row['default'] && $defaultRate > 0.0) {
                    $byCurrency[$currencyId] = $row['default'] * $rate / $defaultRate;
                }
            }

            $prices[$pseId] = new BasePrices($pseId, $row['product_id'], $byCurrency);
        }

        return $prices;
    }
}
