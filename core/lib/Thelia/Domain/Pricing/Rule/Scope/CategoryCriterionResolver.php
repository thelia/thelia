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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CategoryQuery;

/**
 * The products filed in one of the named categories - with, when the rule says so,
 * the products of every category below them. A product filed in several categories
 * matches as soon as one of them is named.
 *
 * The tree is walked afresh on every call, one query per level: the scope is
 * rematerialized right after a category moves, in the same process, and a walk
 * remembered from before the move would file the moved branch where it no longer is.
 */
final class CategoryCriterionResolver implements ScopeCriterionResolverInterface
{
    public function type(): string
    {
        return CatalogPriceRule::CRITERION_CATEGORY;
    }

    public function predicate(array $targetIds, CatalogPriceRule $rule): string
    {
        $categoryIds = $targetIds;

        if ($rule->getIncludeSubcategories()) {
            $categoryIds = [...$categoryIds, ...$this->descendantsOf($targetIds)];
        }

        return \sprintf(
            'EXISTS (SELECT 1 FROM product_category pc WHERE pc.product_id = p.id AND pc.category_id IN %s)',
            SqlIdList::inList($categoryIds),
        );
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<int> every category below the given ones, at any depth
     */
    private function descendantsOf(array $categoryIds): array
    {
        $descendants = [];
        $seen = array_fill_keys($categoryIds, true);
        $frontier = $categoryIds;

        while ([] !== $frontier) {
            /** @var list<int|string> $children */
            $children = CategoryQuery::create()
                ->filterByParent($frontier, Criteria::IN)
                ->select('Id')
                ->find()
                ->getData();

            $frontier = [];

            foreach ($children as $childId) {
                $childId = (int) $childId;

                // A cycle in the parent column must not walk forever.
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $descendants[] = $childId;
                $frontier[] = $childId;
            }
        }

        return $descendants;
    }
}
