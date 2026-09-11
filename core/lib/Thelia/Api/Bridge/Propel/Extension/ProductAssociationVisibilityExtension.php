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
use Thelia\Api\Resource\ProductAssociation;
use Thelia\Api\Resource\ProductAssociationType;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Map\AccessoryTableMap;
use Thelia\Model\Map\ProductAssociationTypeTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductAssociationTypeQuery;

/**
 * Keeps the front relation endpoints to what the shop actually offers.
 *
 * A relation carries the id and, through its relation, the title of the product
 * it points at. Leaving the filtering to the caller — which is what
 * `/front/products` does today, the theme passing `visible=true` itself — would
 * let anyone read the products a shop has taken offline by walking the relations
 * of a product that is still online.
 *
 * Two rules, because there are two ways for a block not to be offered: the
 * product it points at is offline, or the merchant has hidden the type. Both are
 * applied in the query, so the collection and the item read answer the same, and
 * a relation out of reach answers 404 rather than an empty-looking 200.
 *
 * The admin endpoints are left alone: a back-office user reads every relation,
 * hidden types and offline products included — that is the point of the screen.
 */
final readonly class ProductAssociationVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopOffers($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToWhatTheShopOffers($query, $resourceClass, $operation);
    }

    private function scopeToWhatTheShopOffers(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        if (ProductAssociation::class === $resourceClass && $query instanceof AccessoryQuery) {
            $query->where($this->isOneOfClause(
                AccessoryTableMap::COL_ACCESSORY,
                ProductTableMap::COL_ID,
                ProductTableMap::TABLE_NAME,
                ProductTableMap::COL_VISIBLE,
            ));

            $query->where($this->isOneOfClause(
                AccessoryTableMap::COL_TYPE_ID,
                ProductAssociationTypeTableMap::COL_ID,
                ProductAssociationTypeTableMap::TABLE_NAME,
                ProductAssociationTypeTableMap::COL_VISIBLE,
            ));

            return;
        }

        if (ProductAssociationType::class === $resourceClass && $query instanceof ProductAssociationTypeQuery) {
            $query->filterByVisible(1);
        }
    }

    /**
     * A subquery rather than a join: the eager loading extension joins these same
     * tables to hydrate the relation, and a second join of its own would only
     * repeat what it already reads.
     */
    private function isOneOfClause(string $column, string $targetIdColumn, string $targetTable, string $targetVisibleColumn): string
    {
        return \sprintf(
            '%s IN (SELECT %s FROM %s WHERE %s = 1)',
            $column,
            $targetIdColumn,
            $targetTable,
            $targetVisibleColumn,
        );
    }
}
