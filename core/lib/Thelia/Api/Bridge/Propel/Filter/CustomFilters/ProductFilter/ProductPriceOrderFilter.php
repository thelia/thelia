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

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (self::PRICE_ORDER_NAME !== $property) {
            return;
        }

        $direction = strtolower($value);

        if ($direction === strtolower(OrderFilter::DIRECTION_ASC)) {
            $query
                ->useProductSaleElementsQuery()
                ->useProductPriceQuery()
                ->orderByPrice(Criteria::ASC)
                ->endUse()
                ->endUse();
        } elseif ($direction === strtolower(OrderFilter::DIRECTION_DESC)) {
            $query
                ->useProductSaleElementsQuery()
                ->useProductPriceQuery()
                ->orderByPrice(Criteria::DESC)
                ->endUse()
                ->endUse();
        }
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
