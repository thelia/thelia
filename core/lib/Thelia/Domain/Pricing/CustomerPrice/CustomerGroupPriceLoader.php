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

namespace Thelia\Domain\Pricing\CustomerPrice;

use Propel\Runtime\Propel;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Model\Currency;
use Thelia\Model\Map\CustomerGroupProductPriceTableMap;

/**
 * Reads the prices a customer group has of its own for a batch of sale elements, in
 * one statement.
 *
 * Each of the two prices is read on its own: the value typed in the currency asked
 * for when there is one (a row not flagged `from_default_currency`), the value of
 * the default currency converted at the rates otherwise. A price absent from both is
 * no price - the catalog then stands in. A sale element the group prices in neither
 * way is absent; nothing here ever throws.
 */
class CustomerGroupPriceLoader
{
    public function __construct(private readonly CurrencyConverter $currencyConverter)
    {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, CustomerGroupPrice> keyed by sale element id
     */
    public function load(array $productSaleElementsIds, int $customerGroupId, Currency $currency): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $currencyId = (int) $currency->getId();
        $defaultCurrencyId = (int) Currency::getDefaultCurrency()->getId();

        $statement = Propel::getConnection(CustomerGroupProductPriceTableMap::DATABASE_NAME)->prepare(\sprintf(
            'SELECT product_sale_elements_id, currency_id, price, promo_price, from_default_currency
             FROM `customer_group_product_price`
             WHERE customer_group_id = :customerGroupId
               AND currency_id IN (:currencyId, :defaultCurrencyId)
               AND product_sale_elements_id IN (%s)',
            implode(', ', $productSaleElementsIds),
        ));
        $statement->bindValue(':customerGroupId', $customerGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':currencyId', $currencyId, \PDO::PARAM_INT);
        $statement->bindValue(':defaultCurrencyId', $defaultCurrencyId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var array<int, array{explicit: array{0: ?float, 1: ?float}, default: array{0: ?float, 1: ?float}}> $rows */
        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $pseId = (int) $row['product_sale_elements_id'];
            $rows[$pseId] ??= ['explicit' => [null, null], 'default' => [null, null]];
            $pair = [self::amountOf($row['price']), self::amountOf($row['promo_price'])];

            if ((int) $row['currency_id'] === $defaultCurrencyId) {
                $rows[$pseId]['default'] = $pair;
            }

            if ((int) $row['currency_id'] === $currencyId && !$row['from_default_currency']) {
                $rows[$pseId]['explicit'] = $pair;
            }
        }

        $prices = [];

        foreach ($rows as $pseId => $row) {
            $price = $this->inCurrency($row['explicit'][0], $row['default'][0], $currency);
            $promoPrice = $this->inCurrency($row['explicit'][1], $row['default'][1], $currency);

            if (null !== $price || null !== $promoPrice) {
                $prices[$pseId] = new CustomerGroupPrice($pseId, $price, $promoPrice);
            }
        }

        return $prices;
    }

    private function inCurrency(?float $typed, ?float $inDefaultCurrency, Currency $currency): ?float
    {
        if (null !== $typed) {
            return $typed;
        }

        return null === $inDefaultCurrency ? null : $this->currencyConverter->fromDefaultCurrency($inDefaultCurrency, $currency);
    }

    /**
     * A DECIMAL column comes back as a string, NULL when the group has no such price.
     */
    private static function amountOf(mixed $value): ?float
    {
        return null === $value ? null : (float) $value;
    }
}
