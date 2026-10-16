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
use Thelia\Api\Resource\SaleUnit;
use Thelia\Model\SaleUnitQuery;

/**
 * The front only ever sees the active sale units: what a store picker lists,
 * a closed or not-yet-opened unit is not part of. The admin operations are
 * left alone — turning a unit back on requires reading it while inactive.
 */
final readonly class SaleUnitFrontQueryExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filterActiveOnFront($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filterActiveOnFront($query, $resourceClass, $operation);
    }

    private function filterActiveOnFront(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (SaleUnit::class !== $resourceClass) {
            return;
        }

        if (!$operation instanceof HttpOperation || !str_starts_with($operation->getUriTemplate() ?? '', '/front')) {
            return;
        }

        if ($query instanceof SaleUnitQuery) {
            $query->filterByActive(true);
        }
    }
}
