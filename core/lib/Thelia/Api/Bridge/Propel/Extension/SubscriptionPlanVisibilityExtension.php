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
use Thelia\Api\Resource\SubscriptionPlan;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Map\SubscriptionPlanTableMap;
use Thelia\Model\SubscriptionPlanQuery;

/**
 * Keeps the front endpoints of the plans to the plans the merchant shows, on a product the
 * visitor may see: whatever the query string says, the collection and the item read never serve
 * a plan turned off, a plan of an offline product, nor one of a product the catalog hides from
 * this visitor (a private drop they are not part of, a category closed to their group, a rule of
 * a module). Such a plan answers 404 by id, as its product does. The admin endpoints are left
 * alone.
 */
final readonly class SubscriptionPlanVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ProductVisibility $productVisibility,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisiblePlans($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisiblePlans($query, $resourceClass, $operation);
    }

    private function scopeToVisiblePlans(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (SubscriptionPlan::class !== $resourceClass || !$query instanceof SubscriptionPlanQuery || !FrontOperation::isFront($operation)) {
            return;
        }

        $query->filterByVisible(1)->useProductQuery()->filterByVisible(1)->endUse();
        $this->productVisibility->applyTo($query, SubscriptionPlanTableMap::COL_PRODUCT_ID);
    }
}
