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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter;

use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * The product queries whose price order is left to the visitor price sort extension, with
 * the direction asked.
 *
 * The filter that reads "untaxed_price_order" runs before the other filters and the
 * visibility rules have narrowed the collection, while the order on what each visitor pays
 * can only be settled on the narrowed set: the filter leaves a note here, on the query, and
 * the extension that runs once the query is complete reads it. Tied to the query object and
 * to nothing else, so a second collection read in the same request never inherits it.
 */
final class ProductPriceSortRequests
{
    /** @var \WeakMap<ModelCriteria, string> */
    private \WeakMap $directions;

    public function __construct()
    {
        $this->directions = new \WeakMap();
    }

    public function request(ModelCriteria $query, string $direction): void
    {
        $this->directions[$query] = $direction;
    }

    public function directionOn(ModelCriteria $query): ?string
    {
        return $this->directions[$query] ?? null;
    }

    public function forget(ModelCriteria $query): void
    {
        unset($this->directions[$query]);
    }
}
