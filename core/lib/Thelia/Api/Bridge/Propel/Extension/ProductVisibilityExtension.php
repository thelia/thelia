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
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\ProductSaleElements;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Hides from the front callers the products they may not see: the private drops
 * of the reserved operations and the rules of the modules (ProductVisibility).
 *
 * Filtering in the query means the collection, the item read and the count are
 * bounded by the same rule: a product left reachable by its id is not hidden,
 * it is only harder to find. The sale elements are scoped too, since a caller
 * reading /front/product_sale_elements/{id} reaches the price without ever
 * naming the product.
 *
 * The admin endpoints are left alone: they sit behind ROLE_ADMIN, and a
 * back-office user setting an operation up has to see what is in it.
 */
final readonly class ProductVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ProductVisibility $productVisibility,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleProducts($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleProducts($query, $resourceClass, $operation);
    }

    private function scopeToVisibleProducts(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        $productIdColumn = $this->productIdColumnOf($query, $resourceClass);

        if (null === $productIdColumn) {
            return;
        }

        $this->productVisibility->applyTo($query, $productIdColumn);
    }

    private function productIdColumnOf(ModelCriteria $query, string $resourceClass): ?string
    {
        if (Product::class === $resourceClass && $query instanceof ProductQuery) {
            return ProductTableMap::COL_ID;
        }

        if (ProductSaleElements::class === $resourceClass && $query instanceof ProductSaleElementsQuery) {
            return ProductSaleElementsTableMap::COL_PRODUCT_ID;
        }

        return null;
    }
}
