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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\PricingActivityChecker;

/**
 * Sorts the product collection on the untaxed price the visitor pays for the default
 * combination, in the currency the shop is browsed in, with "untaxed_price_order=asc|desc".
 *
 * Two ways to the same order, chosen on whether the price of a product may differ from the
 * public one for this visitor ({@see PricingActivityChecker::mayOrderDifferFromThePublicPrice()}):
 *  - it may not (no group, discount, reserved operation or sale unit pricing for them): the
 *    public price is read in SQL, {@see ProductPublicPriceOrder}, and the database sorts and
 *    paginates as it always did;
 *  - it may: the price of the group, the discounts, the rules and the reserved operations are
 *    resolved in PHP, by the one resolver that prices the cards. The filter only notes the
 *    direction on the query; {@see ProductPriceSortExtension} sorts the narrowed set once the
 *    query is complete.
 *
 * The back office ranks on the catalog price (the `price` column of the default combination,
 * neither the promotion nor the rules), in SQL: {@see ProductPublicPriceOrder::applyCatalogPrice()}.
 *
 * Combined with another "order[...]", the price is the first key and the other order settles
 * the products priced alike, on both ways.
 */
class ProductPriceOrderFilter extends AbstractFilter
{
    private const PRICE_ORDER_NAME = 'untaxed_price_order';

    public function __construct(
        private readonly ProductPublicPriceOrder $publicPriceOrder,
        private readonly ProductPriceSortRequests $sortRequests,
        private readonly VisitorPriceReader $visitorPriceReader,
        private readonly PricingActivityChecker $activityChecker,
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

        if (!FrontOperation::isFront($operation)) {
            $this->publicPriceOrder->applyCatalogPrice($query, $direction);

            return;
        }

        if ($this->activityChecker->mayOrderDifferFromThePublicPrice($this->visitorPriceReader->currentCustomer())) {
            $this->sortRequests->request($query, $direction);

            return;
        }

        $this->publicPriceOrder->apply($query, $direction);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRICE_ORDER_NAME => [
                'property' => self::PRICE_ORDER_NAME,
                'type' => 'string',
                'required' => false,
                'description' => 'Sort on the untaxed price the visitor pays for the default combination (the price of their group, the promotion, the rules and operations in force, the discounts), in the currency browsed. Products without a price come last.',
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
