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

namespace Thelia\Domain\Pricing\Rule\Scope;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Model\CatalogPriceRule;

/**
 * Turns one type of criterion of a rule into the SQL predicate that keeps the sale
 * elements it names.
 *
 * The scope of a rule is materialized by one statement over `product_sale_elements pse`
 * joined to `product p`: each criterion type present on the rule contributes one
 * predicate, and the predicates are combined with AND. The core ships one resolver per
 * type it knows (category, brand, template, feature value, attribute value, product); a
 * module registers its own type by shipping another one - a rule editor then stores
 * criteria of that type, and the materializer asks the module for their predicate.
 *
 * The predicate may only read the `pse` and `p` aliases, and must be self-contained:
 * the target ids are integers the resolver inlines itself, nothing else is bound.
 */
#[AutoconfigureTag(self::TAG)]
interface ScopeCriterionResolverInterface
{
    public const TAG = 'thelia.catalog_price_rule.criterion';

    /**
     * The value of `catalog_price_rule_criterion.type` this resolver answers for.
     */
    public function type(): string;

    /**
     * The SQL predicate keeping the sale elements the given targets name, over the
     * aliases `pse` (product_sale_elements) and `p` (product).
     *
     * @param list<int> $targetIds never empty
     */
    public function predicate(array $targetIds, CatalogPriceRule $rule): string;
}
