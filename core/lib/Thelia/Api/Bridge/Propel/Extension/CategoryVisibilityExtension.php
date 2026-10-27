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
use Thelia\Api\Resource\Category;
use Thelia\Api\Resource\CategoryDocument;
use Thelia\Api\Resource\CategoryImage;
use Thelia\Api\Resource\ProductCategory;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryDocumentTableMap;
use Thelia\Model\Map\CategoryImageTableMap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\ProductCategoryQuery;

/**
 * Hides from the front callers what hangs on a category their customer group is shut out
 * of (CategoryVisibility): the category itself, the product links, the images and the
 * documents of a closed category are out of the collection, and a 404 on the item.
 *
 * Filtering in the query means the collection, the item read and the count are bounded by
 * the same rule, as ProductVisibilityExtension does for products. A resource that carries a
 * category column joins the table below; the admin endpoints are left alone: they sit
 * behind ROLE_ADMIN.
 *
 * The `productCategories` a product embeds are not read through a query of their own:
 * ProductCategoryVisibilityListener narrows that array.
 */
final readonly class CategoryVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /**
     * The resources scoped by the category they sit in: the query class they are read
     * through, and the qualified column holding the category id.
     *
     * @var array<class-string, array{0: class-string<ModelCriteria>, 1: string}>
     */
    private const CATEGORY_COLUMN_BY_RESOURCE = [
        Category::class => [CategoryQuery::class, CategoryTableMap::COL_ID],
        ProductCategory::class => [ProductCategoryQuery::class, ProductCategoryTableMap::COL_CATEGORY_ID],
        CategoryImage::class => [CategoryImageQuery::class, CategoryImageTableMap::COL_CATEGORY_ID],
        CategoryDocument::class => [CategoryDocumentQuery::class, CategoryDocumentTableMap::COL_CATEGORY_ID],
    ];

    public function __construct(
        private CategoryVisibility $categoryVisibility,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOpenCategories($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOpenCategories($query, $resourceClass, $operation);
    }

    private function scopeToOpenCategories(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!FrontOperation::isFront($operation)) {
            return;
        }

        [$queryClass, $categoryIdColumn] = self::CATEGORY_COLUMN_BY_RESOURCE[$resourceClass] ?? [null, null];

        if (null === $queryClass || !$query instanceof $queryClass) {
            return;
        }

        $this->categoryVisibility->applyTo($query, $categoryIdColumn);
    }
}
