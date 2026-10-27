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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Sorts the product collection on the catalog price of its default combination, in the
 * currency the shop is browsed in (the default currency one converted when none was typed
 * in that currency, as the cards are priced), with "untaxed_price_order=asc|desc".
 *
 * The sort is made in SQL, on the public catalog price. What a customer alone is owed - the
 * price of their group, a rule, a reserved operation, their discount or their group's - is
 * resolved per customer in PHP and is not in the database, so it stays out of the sort: a
 * customer sees a listing ordered by the public prices, whose cards may show other ones.
 * Ordering the whole catalog by what each customer pays would resolve every product of it
 * for every sorted request.
 */
class ProductPriceOrderFilter extends AbstractFilter
{
    private const PRICE_ORDER_NAME = 'untaxed_price_order';

    private const PRICE_SORT_KEY = 'product_price_order_key';

    private const SALE_ELEMENTS_JOIN_ALIAS = 'product_price_order_pse';

    private const PRICE_JOIN_ALIAS = 'product_price_order_price';

    private const DEFAULT_CURRENCY_PRICE_JOIN_ALIAS = 'product_price_order_default_price';

    public function __construct(
        private readonly ProductPriceCurrencyListener $currencyResolver,
        private readonly CurrencyConverter $currencyConverter,
    ) {
        parent::__construct();
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (self::PRICE_ORDER_NAME !== $property) {
            return;
        }

        if (!\is_scalar($value)) {
            return;
        }

        $direction = strtoupper((string) $value);

        if (!\in_array($direction, [OrderFilter::DIRECTION_ASC, OrderFilter::DIRECTION_DESC], true)) {
            return;
        }

        // Outer joins: sorting a listing is not filtering it, and a product with no sale
        // element or no price in the asked currency must stay in the collection instead of
        // disappearing the moment the visitor picks a price order. One row per product: the
        // default combination, one price row per currency.
        $currency = $this->currencyResolver->currentCurrency();
        $query->withColumn($this->joinPrices($query, $currency), self::PRICE_SORT_KEY);

        // Priceless products keep a NULL sort key: send them last in both directions.
        $query->addAscendingOrderByColumn('ISNULL('.self::PRICE_SORT_KEY.')');

        if (OrderFilter::DIRECTION_ASC === $direction) {
            $query->addAscendingOrderByColumn(self::PRICE_SORT_KEY);

            return;
        }

        $query->addDescendingOrderByColumn(self::PRICE_SORT_KEY);
    }

    /**
     * @return string the SQL expression of the price to sort on, NULL when the combination has none
     */
    private function joinPrices(ModelCriteria $query, Currency $currency): string
    {
        $query
            ->leftJoinProductSaleElements(self::SALE_ELEMENTS_JOIN_ALIAS)
            ->addJoinCondition(self::SALE_ELEMENTS_JOIN_ALIAS, '`'.self::SALE_ELEMENTS_JOIN_ALIAS.'`.`is_default` = 1');

        $this->joinPriceOf($query, self::PRICE_JOIN_ALIAS, $currency);

        $defaultCurrency = Currency::getDefaultCurrency();
        $ownPrice = '`'.self::PRICE_JOIN_ALIAS.'`.`price`';

        if ($currency->getId() === $defaultCurrency->getId()) {
            return $ownPrice;
        }

        $this->joinPriceOf($query, self::DEFAULT_CURRENCY_PRICE_JOIN_ALIAS, $defaultCurrency);

        // The row of the browsed currency, unless it is missing or flagged as derived from the
        // default currency one, which is then converted at the rate of the shop.
        return \sprintf(
            'CASE WHEN ISNULL(%1$s) OR `%2$s`.`from_default_currency` = 1 THEN `%3$s`.`price` * %4$s ELSE %1$s END',
            $ownPrice,
            self::PRICE_JOIN_ALIAS,
            self::DEFAULT_CURRENCY_PRICE_JOIN_ALIAS,
            $this->currencyConverter->factorAsSql($currency),
        );
    }

    private function joinPriceOf(ModelCriteria $query, string $alias, Currency $currency): void
    {
        $join = new Join();
        $join->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', self::SALE_ELEMENTS_JOIN_ALIAS, ProductPriceTableMap::TABLE_NAME, 'PRODUCT_SALE_ELEMENTS_ID', $alias);
        $join->setJoinType(Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($join, $alias.'_join')
            ->addJoinCondition($alias.'_join', '`'.$alias.'`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRICE_ORDER_NAME => [
                'property' => self::PRICE_ORDER_NAME,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilter::DIRECTION_ASC),
                        strtolower(OrderFilter::DIRECTION_DESC),
                    ],
                ],
            ],
        ];
    }
}
