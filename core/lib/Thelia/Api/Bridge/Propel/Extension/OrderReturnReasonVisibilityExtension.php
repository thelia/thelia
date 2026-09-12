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
use Thelia\Api\Resource\OrderReturnReason;
use Thelia\Model\OrderReturnReasonQuery;

/**
 * Keeps the front endpoints of the return reasons to the reasons the merchant
 * shows.
 *
 * `visible` is what the merchant turns off to retire a reason without deleting
 * it, so that the requests already filed under it keep their label. The front
 * operations carry a `visible` filter, and a filter is something the caller
 * chooses: omitted, or set to false, it served the retired reasons to anyone
 * asking. Filtering in the query means the collection and the item read are
 * both bounded by the merchant's choice, whatever the query string says.
 *
 * The admin endpoints are left alone: the merchant manages the hidden reasons
 * from there, and cannot hide one it can no longer see.
 */
final readonly class OrderReturnReasonVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleReasons($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleReasons($query, $resourceClass, $operation);
    }

    private function scopeToVisibleReasons(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (OrderReturnReason::class !== $resourceClass || !$query instanceof OrderReturnReasonQuery) {
            return;
        }

        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        $query->filterByVisible(true);
    }
}
