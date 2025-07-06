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
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Thelia\Api\Bridge\Propel\Filter\FilterInterface;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;

class FilterExtension implements QueryCollectionExtensionInterface
{
    private $filterLocator;

    public function __construct(
        #[TaggedLocator('thelia.api.propel.filter')] ServiceLocator $filterLocator,
    ) {
        $this->filterLocator = $filterLocator;
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $resourceFilters = $operation?->getFilters();

        if ($resourceFilters === null || $resourceFilters === []) {
            return;
        }

        $orderFilters = [];

        foreach ($resourceFilters as $filterId) {
            $filter = $this->filterLocator->has($filterId) ? $this->filterLocator->get($filterId) : null;

            if ($filter instanceof FilterInterface) {
                // Apply the OrderFilter after every other filter to avoid an edge case where OrderFilter would do a LEFT JOIN instead of an INNER JOIN
                if ($filter instanceof OrderFilter) {
                    $orderFilters[] = $filter;
                    continue;
                }

                $context['filters'] ??= [];
                $filter->apply($query, $resourceClass, $operation, $context);
            }
        }

        foreach ($orderFilters as $orderFilter) {
            $context['filters'] ??= [];
            $orderFilter->apply($query, $resourceClass, $operation, $context);
        }

        if (\is_callable([$query, 'groupById'])) {
            $query->groupById();
        }
    }
}
