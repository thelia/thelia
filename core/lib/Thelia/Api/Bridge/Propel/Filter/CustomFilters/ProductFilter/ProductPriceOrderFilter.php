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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;

class ProductPriceOrderFilter extends AbstractFilter
{
    private const PRICE_ORDER_NAME = 'untaxed_price_order';

    private const SALE_ELEMENTS_JOIN_ALIAS = 'product_price_order_pse';

    private const PRICE_JOIN_ALIAS = 'product_price_order_price';

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
        // disappearing the moment the visitor picks a price order.
        $query
            ->useProductSaleElementsQuery(self::SALE_ELEMENTS_JOIN_ALIAS, Criteria::LEFT_JOIN)
                ->useProductPriceQuery(self::PRICE_JOIN_ALIAS, Criteria::LEFT_JOIN)
                ->endUse()
            ->endUse();

        $priceColumn = self::PRICE_JOIN_ALIAS.'.price';

        // Priceless products keep a NULL sort key: send them last in both directions.
        $query->addAscendingOrderByColumn('ISNULL('.$priceColumn.')');

        if (OrderFilter::DIRECTION_ASC === $direction) {
            $query->addAscendingOrderByColumn($priceColumn);

            return;
        }

        $query->addDescendingOrderByColumn($priceColumn);
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
