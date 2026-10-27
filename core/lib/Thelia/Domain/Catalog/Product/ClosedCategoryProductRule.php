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

namespace Thelia\Domain\Catalog\Product;

use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Model\Customer;

/**
 * The products a customer group may not see for its categories.
 *
 * A group restricted to categories sees a product when at least one of its categories is open
 * to the group: listed, or below a listed one ({@see CategoryVisibility}). A product filed
 * only in passing or closed categories is hidden, which is every product filed somewhere when
 * the list is empty. A product that belongs to no category is
 * not concerned and stays visible, as it was before categories could be restricted: no
 * category means no shelf to close, and a merchant who files nothing restricts nothing.
 *
 * It follows the contract of ProductVisibilityRuleInterface (same signature, null when it
 * hides nothing) without implementing it: ProductVisibility takes it by name, so that the
 * data access cache, which steps aside as soon as a module declares a rule, only does for
 * this one while a group is actually restricted to some categories.
 */
final readonly class ClosedCategoryProductRule
{
    public function __construct(
        private CategoryVisibility $categoryVisibility,
    ) {
    }

    public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
    {
        $openCategoryIds = $this->categoryVisibility->openCategoryIdsFor($customer);

        if (null === $openCategoryIds) {
            return null;
        }

        // A restricted group whose list is empty (the category it listed was deleted) keeps
        // the products filed nowhere, and nothing else: no `IN ()`, which is no SQL.
        if ([] === $openCategoryIds) {
            return \sprintf('NOT EXISTS (SELECT 1 FROM product_category filed_product_category WHERE filed_product_category.product_id = %s)', $productIdColumn);
        }

        return \sprintf(
            'NOT EXISTS (SELECT 1 FROM product_category filed_product_category WHERE filed_product_category.product_id = %1$s)'
            .' OR EXISTS (SELECT 1 FROM product_category open_product_category WHERE open_product_category.product_id = %1$s AND open_product_category.category_id IN (%2$s))',
            $productIdColumn,
            implode(', ', array_map('intval', $openCategoryIds)),
        );
    }

    public function isActive(): bool
    {
        return $this->categoryVisibility->hasRestrictions();
    }
}
