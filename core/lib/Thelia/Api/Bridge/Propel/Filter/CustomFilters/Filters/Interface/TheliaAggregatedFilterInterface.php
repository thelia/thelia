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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface;

/**
 * A filter that can list its facet values for a whole result set at once.
 *
 * TheliaFilterInterface::getValue() answers for one record, so building a facet
 * list with it costs at least one query per record. A filter implementing this
 * interface is asked once for the identifiers of the whole set instead, and is
 * expected to let the database do the aggregation.
 */
interface TheliaAggregatedFilterInterface
{
    /**
     * @param array<int> $resourceIds identifiers of the records the facets must describe
     *
     * @return array<\Thelia\Api\Resource\FilterValue>
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array;
}
