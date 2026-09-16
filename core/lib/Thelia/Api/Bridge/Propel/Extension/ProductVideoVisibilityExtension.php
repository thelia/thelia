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
use Thelia\Api\Resource\ProductSaleElementsProductVideo;
use Thelia\Api\Resource\ProductVideo;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Model\Map\ProductSaleElementsProductVideoTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\ProductVideoTableMap;
use Thelia\Model\ProductSaleElementsProductVideoQuery;
use Thelia\Model\ProductVideoQuery;

/**
 * Keeps the front video endpoints to what the shop actually shows.
 *
 * A video the merchant hid, one hanging off a product taken offline, or one of a
 * product belonging to a private drop the visitor is not part of, is not offered
 * on the shop — and a theme passing `visible=true` itself is not what decides it:
 * the filter is the caller's, this rule is the shop's. All are applied in the
 * query, so the collection and the item read answer the same and a video out of
 * reach answers 404 rather than an empty-looking 200.
 *
 * The combination links carry the same rule. They hold nothing but identifiers,
 * which is precisely what makes them worth closing: walking them was a way to
 * learn which videos and which products exist behind what the shop shows.
 *
 * The admin endpoints are left alone: a back-office user reads every video,
 * hidden ones and offline products included — that is the point of the screen.
 */
final readonly class ProductVideoVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ReservedSaleVisibility $reservedSaleVisibility,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopShows($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopShows($query, $resourceClass, $operation);
    }

    private function scopeToWhatTheShopShows(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        if (ProductVideo::class === $resourceClass && $query instanceof ProductVideoQuery) {
            $query->filterByVisible(1);

            // A subquery rather than a join: the eager loading extension already
            // joins the product to hydrate the relation, and a second join would
            // only repeat what it reads.
            $query->where($this->onAVisibleProductClause(ProductVideoTableMap::COL_PRODUCT_ID));
            $this->reservedSaleVisibility->applyTo($query, ProductVideoTableMap::COL_PRODUCT_ID);

            return;
        }

        if (ProductSaleElementsProductVideo::class === $resourceClass && $query instanceof ProductSaleElementsProductVideoQuery) {
            // The link carries no product of its own, so both rules are asked of
            // the video it points at, in one subquery each.
            $query->where(\sprintf(
                '%s IN (SELECT %s FROM %s WHERE %s = 1)',
                ProductSaleElementsProductVideoTableMap::COL_PRODUCT_VIDEO_ID,
                ProductVideoTableMap::COL_ID,
                ProductVideoTableMap::TABLE_NAME,
                ProductVideoTableMap::COL_VISIBLE,
            ));

            $query->where(\sprintf(
                '%s IN (SELECT %s FROM %s WHERE %s)',
                ProductSaleElementsProductVideoTableMap::COL_PRODUCT_VIDEO_ID,
                ProductVideoTableMap::COL_ID,
                ProductVideoTableMap::TABLE_NAME,
                $this->onAVisibleProductClause(ProductVideoTableMap::COL_PRODUCT_ID),
            ));
        }
    }

    private function onAVisibleProductClause(string $productIdColumn): string
    {
        return \sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s = 1)',
            $productIdColumn,
            ProductTableMap::COL_ID,
            ProductTableMap::TABLE_NAME,
            ProductTableMap::COL_VISIBLE,
        );
    }
}
