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
use Thelia\Model\SubscriptionPlanQuery;

/**
 * Keeps the front endpoints of the plans to the plans the merchant shows, on a product the
 * shop shows: whatever the query string says, the collection and the item read never serve a
 * plan turned off or a plan of an offline product. The admin endpoints are left alone.
 */
final readonly class SubscriptionPlanVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
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
    }
}
