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
use Thelia\Api\Resource\Sale;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Model\SaleQuery;

/**
 * Scopes the front sale endpoints to the operations the caller is part of.
 *
 * An operation is reached by a sequential numeric id: without this, guessing one
 * is enough to read a private drop the shop has not opened yet, its dates and the
 * products it holds. Filtering in the query means the collection and the item
 * read are bounded by the same rule, and an operation out of reach answers 404
 * rather than an empty-looking 200.
 *
 * A draft is out too. `sale.active` is what the back office and the scheduled
 * command maintain, and an inactive operation is not part of the shop yet.
 *
 * The admin endpoints are left alone: they sit behind ROLE_ADMIN, and a
 * back-office user reads every operation, drafts included.
 */
final readonly class SaleAudienceExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private ReservedSaleVisibility $reservedSaleVisibility,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleSales($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToVisibleSales($query, $resourceClass, $operation);
    }

    private function scopeToVisibleSales(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (Sale::class !== $resourceClass || !$query instanceof SaleQuery) {
            return;
        }

        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        $query->filterByActive(true);

        $this->reservedSaleVisibility->applyToSales($query);
    }
}
