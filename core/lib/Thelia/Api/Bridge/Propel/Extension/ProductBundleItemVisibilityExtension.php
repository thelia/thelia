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

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\ProductBundleItem;
use Thelia\Domain\Catalog\Bundle\BundleComponentReader;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Map\ProductBundleItemTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductBundleItemQuery;

/**
 * Keeps the front endpoints of the components of a bundle to what a visitor can buy: the
 * bundle online (and out of a private drop the visitor is not part of), and a component that
 * can be sold (BundleComponentReader): its sale element still there and online, its product
 * online and not a bundle. A component taken offline is not listed, and a line read by its id
 * answers 404 like one that does not exist. The admin endpoints read every line.
 *
 * What the visitor may see applies to the component as to the bundle (ProductVisibility: a
 * private drop, a rule of a module): a component reserved to others is not listed, and its
 * line answers 404, as the bundle would be bought without it by nobody.
 */
final readonly class ProductBundleItemVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private ProductVisibility $productVisibility)
    {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopSells($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopSells($query, $resourceClass, $operation);
    }

    private function scopeToWhatTheShopSells(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (ProductBundleItem::class !== $resourceClass || !$query instanceof ProductBundleItemQuery || !$operation instanceof HttpOperation || !str_starts_with((string) $operation->getUriTemplate(), '/front/')) {
            return;
        }

        $query->where(\sprintf(
            '%s IN (%s)',
            ProductBundleItemTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
            BundleComponentReader::sellableSaleElementsSubquery(),
        ));

        $query->where(\sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s = 1 AND %s = 1)',
            ProductBundleItemTableMap::COL_BUNDLE_PRODUCT_ID,
            ProductTableMap::COL_ID,
            ProductTableMap::TABLE_NAME,
            ProductTableMap::COL_VISIBLE,
            ProductTableMap::COL_IS_BUNDLE,
        ));

        $this->productVisibility->applyTo($query, ProductBundleItemTableMap::COL_BUNDLE_PRODUCT_ID);

        $visibleComponent = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null !== $visibleComponent) {
            $query->where(\sprintf(
                '%s IN (SELECT %s FROM %s WHERE %s)',
                ProductBundleItemTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
                ProductSaleElementsTableMap::COL_ID,
                ProductSaleElementsTableMap::TABLE_NAME,
                $visibleComponent,
            ));
        }
    }
}
