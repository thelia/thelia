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

/**
 * What the rules that apply to one customer open and close, the categories already
 * expanded to their sub-trees.
 *
 * The ids are integers read from the database: the product rule writes them into a statement
 * as they are.
 */
final readonly class CatalogRestrictionScope
{
    /**
     * @param list<int> $allowedCategoryIds categories targeted by an allow rule, sub-trees included
     * @param list<int> $allowedBrandIds
     * @param list<int> $allowedProductIds
     * @param list<int> $deniedCategoryIds  categories targeted by a deny rule, sub-trees included
     * @param list<int> $deniedBrandIds
     * @param list<int> $deniedProductIds
     */
    public function __construct(
        public array $allowedCategoryIds = [],
        public array $allowedBrandIds = [],
        public array $allowedProductIds = [],
        public array $deniedCategoryIds = [],
        public array $deniedBrandIds = [],
        public array $deniedProductIds = [],
    ) {
    }

    /**
     * Whether the customer has an allow list: what it does not open is then closed.
     */
    public function hasAllowList(): bool
    {
        return [] !== $this->allowedCategoryIds || [] !== $this->allowedBrandIds || [] !== $this->allowedProductIds;
    }

    public function isEmpty(): bool
    {
        return !$this->hasAllowList()
            && [] === $this->deniedCategoryIds
            && [] === $this->deniedBrandIds
            && [] === $this->deniedProductIds;
    }
}
