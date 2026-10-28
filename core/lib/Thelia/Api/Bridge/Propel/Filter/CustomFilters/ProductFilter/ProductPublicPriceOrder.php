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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductQuery;

/**
 * Orders a product query, in SQL, on the price of the default combination, either the public
 * one ({@see apply()}) or the catalog one ({@see applyCatalogPrice()}).
 *
 * The public price is the one every visitor is owed when nothing prices the catalog for who is
 * asking, which the storefront ranks on.
 *
 * The public price is, in the currency the shop is browsed in (the default currency one
 * converted when none was typed in that currency, as the cards are priced):
 *  - the price of the rule open to everyone in force now, read from the stored segments of
 *    `catalog_price_rule_price` as {@see \Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader}
 *    does, when a rule is running; else
 *  - the promotional price while the combination is on promotion; else
 *  - the catalog price.
 *
 * The catalog price is the `price` column alone, whatever the promotion or the rules: what the
 * back office ranks on, as it always did. In the currency browsed all the same, converted from
 * the default currency one in the same way.
 *
 * Outer joins: sorting a listing is not filtering it, and a product with no sale element or
 * no price in the asked currency must stay in the collection. One row per product: the
 * default combination, one price row per currency, one segment per instant.
 */
class ProductPublicPriceOrder
{
    private const PRICE_SORT_KEY = 'product_price_order_key';

    private const SALE_ELEMENTS_JOIN_ALIAS = 'product_price_order_pse';

    private const PRICE_JOIN_ALIAS = 'product_price_order_price';

    private const DEFAULT_CURRENCY_PRICE_JOIN_ALIAS = 'product_price_order_default_price';

    private const RULE_PRICE_JOIN_ALIAS = 'product_price_order_rule';

    public function __construct(
        private readonly ProductPriceCurrencyListener $currencyResolver,
        private readonly CurrencyConverter $currencyConverter,
        private readonly PricingActivityChecker $activityChecker,
    ) {
    }

    /**
     * Makes the price the first thing the query is ordered on, in front of the orders it
     * already carries, which then settle the products priced alike.
     */
    public function apply(ModelCriteria $query, string $direction): void
    {
        $this->order($query, $direction, publicPrice: true);
    }

    /**
     * The same, on the catalog price of the default combination: neither the promotion nor a
     * rule is read.
     */
    public function applyCatalogPrice(ModelCriteria $query, string $direction): void
    {
        $this->order($query, $direction, publicPrice: false);
    }

    private function order(ModelCriteria $query, string $direction, bool $publicPrice): void
    {
        $otherOrders = $query->getOrderByColumns();
        $query->clearOrderByColumns();

        $query->withColumn($this->joinPrices($query, $this->currencyResolver->currentCurrency(), $publicPrice), self::PRICE_SORT_KEY);

        // Priceless products keep a NULL sort key: send them last in both directions.
        $query->addAscendingOrderByColumn('ISNULL('.self::PRICE_SORT_KEY.')');

        if (OrderFilter::DIRECTION_ASC === $direction) {
            $query->addAscendingOrderByColumn(self::PRICE_SORT_KEY);
        } else {
            $query->addDescendingOrderByColumn(self::PRICE_SORT_KEY);
        }

        foreach ($otherOrders as $order) {
            if (1 === preg_match('/^(.*)\s+(ASC|DESC)$/is', $order, $parts)) {
                'DESC' === strtoupper($parts[2]) ? $query->addDescendingOrderByColumn($parts[1]) : $query->addAscendingOrderByColumn($parts[1]);
            }
        }
    }

    /**
     * @return string the SQL expression of the price to sort on, NULL when the combination has none
     */
    private function joinPrices(ModelCriteria $query, Currency $currency, bool $publicPrice): string
    {
        \assert($query instanceof ProductQuery);

        $query
            ->leftJoinProductSaleElements(self::SALE_ELEMENTS_JOIN_ALIAS)
            ->addJoinCondition(self::SALE_ELEMENTS_JOIN_ALIAS, '`'.self::SALE_ELEMENTS_JOIN_ALIAS.'`.`is_default` = 1');

        $this->joinPriceOf($query, self::PRICE_JOIN_ALIAS, $currency);

        $catalogPrice = $this->catalogPrice($query, $currency, $publicPrice);

        if (!$publicPrice || !$this->activityChecker->hasActivePublicRule()) {
            return $catalogPrice;
        }

        $this->joinRulePrice($query, $currency);

        return 'COALESCE(`'.self::RULE_PRICE_JOIN_ALIAS.'`.`price`, '.$catalogPrice.')';
    }

    private function catalogPrice(ModelCriteria $query, Currency $currency, bool $withPromotion): string
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $own = $this->priceOf(self::PRICE_JOIN_ALIAS, $withPromotion);

        if ($currency->getId() === $defaultCurrency->getId()) {
            return $own;
        }

        $this->joinPriceOf($query, self::DEFAULT_CURRENCY_PRICE_JOIN_ALIAS, $defaultCurrency);

        // The row of the browsed currency, unless it is missing or flagged as derived from the
        // default currency one, which is then converted at the rate of the shop.
        return \sprintf(
            'CASE WHEN ISNULL(`%1$s`.`price`) OR `%1$s`.`from_default_currency` = 1 THEN %2$s * %3$s ELSE %4$s END',
            self::PRICE_JOIN_ALIAS,
            $this->priceOf(self::DEFAULT_CURRENCY_PRICE_JOIN_ALIAS, $withPromotion),
            $this->currencyConverter->factorAsSql($currency),
            $own,
        );
    }

    /**
     * The promotional price while the sale element is on promotion, the price otherwise; or the
     * price alone.
     */
    private function priceOf(string $priceAlias, bool $withPromotion): string
    {
        if (!$withPromotion) {
            return \sprintf('`%s`.`price`', $priceAlias);
        }

        return \sprintf(
            'CASE WHEN `%1$s`.`promo` = 1 THEN `%2$s`.`promo_price` ELSE `%2$s`.`price` END',
            self::SALE_ELEMENTS_JOIN_ALIAS,
            $priceAlias,
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

    /**
     * The segment of the public rule price the current instant falls in, in the browsed
     * currency: the window the stored segments carry, read at the instant PublicPriceReader
     * reads it at.
     */
    private function joinRulePrice(ModelCriteria $query, Currency $currency): void
    {
        $alias = self::RULE_PRICE_JOIN_ALIAS;
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $join = new Join();
        $join->addExplicitCondition(ProductSaleElementsTableMap::TABLE_NAME, 'ID', self::SALE_ELEMENTS_JOIN_ALIAS, PublicPriceSegmentWriter::TABLE, 'PRODUCT_SALE_ELEMENTS_ID', $alias);
        $join->setJoinType(Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($join, $alias.'_join')
            ->addJoinCondition($alias.'_join', '`'.$alias.'`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT)
            ->addJoinCondition($alias.'_join', \sprintf('(`%1$s`.`valid_from` IS NULL OR `%1$s`.`valid_from` <= \'%2$s\')', $alias, $now))
            ->addJoinCondition($alias.'_join', \sprintf('(`%1$s`.`valid_until` IS NULL OR `%1$s`.`valid_until` > \'%2$s\')', $alias, $now));
    }
}
