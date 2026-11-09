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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the set of records the facets of a listing are read from, for a project whose
 * listings show only part of the catalogue: the points of sale a product is sold in, a market,
 * a customer segment. A value held only by a record the listing never shows is then neither
 * offered nor counted.
 *
 * The facets already follow the visibility rules of ProductVisibilityRuleInterface; this is for
 * a restriction that must not apply to the cart, the product page or the other readers of that
 * rule, only to what the facets describe.
 *
 * Every scope is called once for each set the facets read (the narrowed set, and the sets
 * relaxed of one group when a value is checked), with the query on the filtered resource
 * (a ProductQuery for `products`) and the resource name. It adds its condition with
 * `where()` or `addAnd()`, never `add()`, which would replace a condition already set on the
 * same column. It reads the visitor and the context itself, and does nothing for a query or a
 * resource it does not concern. The condition is written into the statement as it is: nothing
 * is bound, and every value in it is an integer read from the database or cast with (int).
 *
 * Declaring none costs nothing: no iteration, no query, no criterion. A scope costs the
 * conditions it adds to the statements the facets already run, never a statement of its own
 * per listing: write it as one SQL condition, not as a loop over the records.
 *
 * The facets endpoint is not among the paths of the data access cache, so a scope that follows
 * the visitor needs no cache key of its own on the core side.
 */
#[AutoconfigureTag(self::TAG)]
interface FacetScopeInterface
{
    public const TAG = 'api.thelia.facet_scope';

    public function restrict(ModelCriteria $query, string $resource): void;
}
