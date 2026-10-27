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
use Thelia\Domain\Pricing\CurrencyConverter;
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
 *
 * The promo price and the promo flag come along in the same statement and follow the
 * same currency rule, for the readers that start from the whole catalog price.
 */
class BasePriceLoader
{
    public function __construct(private readonly CurrencyConverter $currencyConverter)
    {
    }

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

        $defaultCurrencyId = (int) Currency::getDefaultCurrency()->getId();
        $currencies = CurrencyQuery::create()->find();

        $statement = Propel::getConnection(ProductPriceTableMap::DATABASE_NAME)->query(\sprintf(
            'SELECT pse.id AS pse_id, pse.product_id AS product_id, pse.promo AS promo, pp.currency_id AS currency_id, pp.price AS price, pp.promo_price AS promo_price, pp.from_default_currency AS from_default_currency
             FROM `product_sale_elements` pse
             LEFT JOIN `product_price` pp ON pp.product_sale_elements_id = pse.id
             WHERE pse.id IN (%s)',
            implode(', ', $productSaleElementsIds),
        ));

        /** @var array<int, array{product_id: int, promo: bool, explicit: array<int, array{float, float}>, default: array{float, float}|null}> $rows */
        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $pseId = (int) $row['pse_id'];
            $rows[$pseId] ??= ['product_id' => (int) $row['product_id'], 'promo' => (bool) $row['promo'], 'explicit' => [], 'default' => null];

            if (null === $row['currency_id']) {
                continue;
            }

            $currencyId = (int) $row['currency_id'];
            $pair = [(float) $row['price'], (float) $row['promo_price']];

            if ($currencyId === $defaultCurrencyId) {
                $rows[$pseId]['default'] = $pair;
            }

            if (!$row['from_default_currency']) {
                $rows[$pseId]['explicit'][$currencyId] = $pair;
            }
        }

        $prices = [];

        foreach ($rows as $pseId => $row) {
            $priceByCurrency = [];
            $promoPriceByCurrency = [];

            foreach ($currencies as $currency) {
                $currencyId = (int) $currency->getId();

                if (isset($row['explicit'][$currencyId])) {
                    [$priceByCurrency[$currencyId], $promoPriceByCurrency[$currencyId]] = $row['explicit'][$currencyId];

                    continue;
                }

                if (null === $row['default']) {
                    continue;
                }

                $price = $this->currencyConverter->fromDefaultCurrency($row['default'][0], $currency);
                $promoPrice = $this->currencyConverter->fromDefaultCurrency($row['default'][1], $currency);

                if (null !== $price && null !== $promoPrice) {
                    $priceByCurrency[$currencyId] = $price;
                    $promoPriceByCurrency[$currencyId] = $promoPrice;
                }
            }

            $prices[$pseId] = new BasePrices($pseId, $row['product_id'], $priceByCurrency, $promoPriceByCurrency, $row['promo']);
        }

        return $prices;
    }
}
