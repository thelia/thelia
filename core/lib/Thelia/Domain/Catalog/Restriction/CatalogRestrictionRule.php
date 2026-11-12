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

namespace Thelia\Domain\Catalog\Restriction;

use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Customer;

/**
 * The products the catalog restrictions of a customer close, as a rule of the product
 * visibility, given to ProductVisibility apart from the rules of the modules (it does not carry
 * their tag, so it never counts as a module's): lists, search, direct reads, sale elements,
 * sitemap and cart all go through it (see {@see CatalogRestrictionResolver} for what the rules say).
 *
 * The clause is true for a product left visible: an allow list becomes one parenthesised
 * disjunction (a product, a brand or a category opens it), each deny rule one more
 * conjunct. The tables of the subqueries are aliased and the ids are integers read from the
 * database, as the contract of ProductVisibilityRuleInterface asks.
 */
final readonly class CatalogRestrictionRule
{
    public function __construct(
        private CatalogRestrictionResolver $resolver,
        private CatalogActivityProbe $catalogActivityProbe,
    ) {
    }

    /**
     * Whether a restriction exists anywhere: read from the statement the front runs anyway.
     */
    public function isActive(): bool
    {
        return $this->catalogActivityProbe->read()->hasCatalogRestrictions;
    }

    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
    {
        $scope = $this->resolver->scopeFor($customer);

        return null === $scope ? null : CatalogRestrictionClauses::visibleProduct($scope, $productIdColumn);
    }
}
