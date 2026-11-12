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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\Product;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;

/**
 * Keeps a price that is hidden from the visitor from being told by a range on the price.
 *
 * The product collection lets a caller narrow it to an interval of the price of its sale elements
 * (`productSaleElements.productPrices.price[gt]=40`, and the promotional price). Asked of a product
 * whose price the visitor is not shown, two calls bracket the figure. A collection narrowed on a
 * price therefore holds only the products whose price the visitor is shown, as the price filter of
 * the themes and the order on the price do: nothing is changed for a visitor shown every price, nor
 * for a request that names no price.
 *
 * Front reads only: the back office filters on the catalog.
 */
final readonly class PriceRangeVisibilityExtension implements QueryCollectionExtensionInterface
{
    /** The filter names, without dots, underscores or case, that compare a price. */
    private const PRICE_FILTERS = ['productpricesprice', 'productpricespromoprice'];

    public function __construct(
        private PriceVisibilityResolver $priceVisibilityResolver,
        private CurrentCustomerProvider $currentCustomerProvider,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!FrontOperation::isFront($operation) || Product::class !== $resourceClass || !$query instanceof ProductQuery) {
            return;
        }

        if (!$this->namesAPrice($context['filters'] ?? [])) {
            return;
        }

        $clause = $this->priceVisibilityResolver->shownPriceClause(ProductTableMap::COL_ID, $this->currentCustomerProvider->getCurrentCustomer());

        if (null !== $clause) {
            $query->where($clause);
        }
    }

    private function namesAPrice(mixed $filters): bool
    {
        if (!\is_array($filters)) {
            return false;
        }

        foreach (array_keys($filters) as $name) {
            $flat = strtolower(str_replace(['.', '_'], '', (string) $name));

            foreach (self::PRICE_FILTERS as $priceFilter) {
                if (str_contains($flat, $priceFilter)) {
                    return true;
                }
            }
        }

        return false;
    }
}
