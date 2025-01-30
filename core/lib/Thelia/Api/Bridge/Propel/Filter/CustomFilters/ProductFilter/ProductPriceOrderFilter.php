<?php

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

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductQuery;

class ProductPriceOrderFilter extends AbstractFilter
{
    private const PRICE_ORDER_NAME = 'untaxed_price_order';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== self::PRICE_ORDER_NAME) {
            return;
        }
        $priceOrder = $value;
        $priceOrder = strtoupper($priceOrder);
        if (!$query instanceof ProductQuery || !\in_array($priceOrder, [Criteria::DESC, Criteria::ASC], true)) {
            return;
        }
        /* ProductQuery $query * */
        $query
            ->useProductSaleElementsQuery()
            ->filterByIsDefault(true)
                ->useProductPriceQuery()
                ->withColumn(
                    sprintf(
                        'IF(%s = 1, %s, %s)',
                        ProductSaleElementsTableMap::COL_PROMO,
                        ProductPriceTableMap::COL_PROMO_PRICE,
                        ProductPriceTableMap::COL_PRICE
                    ),
                    'untaxed_price'
                )
                ->where(
                    sprintf(
                        '(
                            (EXISTS (
                                SELECT 1 FROM %s AS pp
                                WHERE pp.%s = %s
                                GROUP BY pp.%s
                                HAVING COUNT(DISTINCT pp.%s) > 1
                            ) AND %s = 1)
                            OR NOT EXISTS (
                                SELECT 1 FROM %s AS pp
                                WHERE pp.%s = %s
                                GROUP BY pp.%s
                                HAVING COUNT(DISTINCT pp.%s) > 1
                            )
                        )',
                        ProductPriceTableMap::TABLE_NAME,
                        explode(separator: '.', string: ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)[1],
                        ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
                        explode(separator: '.', string: ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)[1],
                        explode(separator: '.', string: ProductPriceTableMap::COL_CURRENCY_ID)[1],
                        ProductPriceTableMap::COL_FROM_DEFAULT_CURRENCY,
                        ProductPriceTableMap::TABLE_NAME,
                        explode(separator: '.', string: ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)[1],
                        ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
                        explode(separator: '.', string: ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)[1],
                        explode(separator: '.', string: ProductPriceTableMap::COL_CURRENCY_ID)[1]
                    )
                )
                ->endUse()
            ->endUse()
        ->orderBy('untaxed_price', $priceOrder);
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
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ],
        ];
    }
}
