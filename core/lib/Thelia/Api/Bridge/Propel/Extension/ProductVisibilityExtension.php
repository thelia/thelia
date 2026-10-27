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
use Thelia\Api\Resource\AttributeCombination;
use Thelia\Api\Resource\FeatureProduct;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\ProductAssociatedContent;
use Thelia\Api\Resource\ProductCategory;
use Thelia\Api\Resource\ProductDocument;
use Thelia\Api\Resource\ProductImage;
use Thelia\Api\Resource\ProductPrice;
use Thelia\Api\Resource\ProductSaleElements;
use Thelia\Api\Resource\ProductSaleElementsProductImage;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductAssociatedContentTableMap;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\Map\ProductDocumentTableMap;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsProductImageTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Hides from the front callers the products they may not see: the private drops
 * of the reserved operations and the rules of the modules (ProductVisibility).
 *
 * Filtering in the query means the collection, the item read and the count are
 * bounded by the same rule: a product left reachable by its id is not hidden,
 * it is only harder to find. The sale elements are scoped too, since a caller
 * reading /front/product_sale_elements/{id} reaches the price without ever
 * naming the product; so are the images and the documents of a product, which
 * would otherwise show what the product holds, the prices, the attribute
 * combinations of its sale elements (read through the sale element), its feature
 * values and its associated contents, the links between a sale
 * element and an image, which carry nothing but the identifiers of both, and
 * the links between a product and a category, which would name a product the
 * visitor may not see in a category they may browse.
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
        if (!FrontOperation::isFront($operation)) {
            return;
        }

        if (ProductSaleElementsProductImage::class === $resourceClass && $query instanceof ProductSaleElementsProductImageQuery) {
            $this->scopeImageLinks($query);

            return;
        }

        if ($query instanceof ProductPriceQuery && ProductPrice::class === $resourceClass) {
            $this->scopeThroughSaleElements($query, ProductPriceTableMap::COL_PRODUCT_SALE_ELEMENTS_ID);

            return;
        }

        if ($query instanceof AttributeCombinationQuery && AttributeCombination::class === $resourceClass) {
            $this->scopeThroughSaleElements($query, AttributeCombinationTableMap::COL_PRODUCT_SALE_ELEMENTS_ID);

            return;
        }

        $productIdColumn = $this->productIdColumnOf($query, $resourceClass);

        if (null === $productIdColumn) {
            return;
        }

        $this->productVisibility->applyTo($query, $productIdColumn);
    }

    /**
     * The link carries no product of its own: every rule is asked of the image it points
     * at, in a subquery, as ProductVideoVisibilityExtension does for the video links.
     */
    private function scopeImageLinks(ProductSaleElementsProductImageQuery $query): void
    {
        $visibleProductClause = $this->productVisibility->visibleProductClause(ProductImageTableMap::COL_PRODUCT_ID);

        if (null === $visibleProductClause) {
            return;
        }

        $query->where(\sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s)',
            ProductSaleElementsProductImageTableMap::COL_PRODUCT_IMAGE_ID,
            ProductImageTableMap::COL_ID,
            ProductImageTableMap::TABLE_NAME,
            $visibleProductClause,
        ));
    }

    /**
     * A row that names a sale element and no product: the product rules are asked of the
     * product the sale element belongs to, in a subquery.
     */
    private function scopeThroughSaleElements(ModelCriteria $query, string $saleElementIdColumn): void
    {
        $visibleProductClause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null === $visibleProductClause) {
            return;
        }

        $query->where(\sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s)',
            $saleElementIdColumn,
            ProductSaleElementsTableMap::COL_ID,
            ProductSaleElementsTableMap::TABLE_NAME,
            $visibleProductClause,
        ));
    }

    private function productIdColumnOf(ModelCriteria $query, string $resourceClass): ?string
    {
        if (Product::class === $resourceClass && $query instanceof ProductQuery) {
            return ProductTableMap::COL_ID;
        }

        if (ProductSaleElements::class === $resourceClass && $query instanceof ProductSaleElementsQuery) {
            return ProductSaleElementsTableMap::COL_PRODUCT_ID;
        }

        if (ProductCategory::class === $resourceClass && $query instanceof ProductCategoryQuery) {
            return ProductCategoryTableMap::COL_PRODUCT_ID;
        }

        if (ProductImage::class === $resourceClass && $query instanceof ProductImageQuery) {
            return ProductImageTableMap::COL_PRODUCT_ID;
        }

        if (ProductDocument::class === $resourceClass && $query instanceof ProductDocumentQuery) {
            return ProductDocumentTableMap::COL_PRODUCT_ID;
        }

        if (FeatureProduct::class === $resourceClass && $query instanceof FeatureProductQuery) {
            return FeatureProductTableMap::COL_PRODUCT_ID;
        }

        if (ProductAssociatedContent::class === $resourceClass && $query instanceof ProductAssociatedContentQuery) {
            return ProductAssociatedContentTableMap::COL_PRODUCT_ID;
        }

        return null;
    }
}
