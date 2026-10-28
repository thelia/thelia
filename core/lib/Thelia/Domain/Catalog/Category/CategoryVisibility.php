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

namespace Thelia\Domain\Catalog\Category;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Map\CategoryTableMap;

/**
 * Which categories a customer group may browse, in one place, so that the API extension,
 * the category page, the loops and the product rule can never disagree.
 *
 * A group is either restricted (customer_group.restricts_categories, set by the writer of its
 * list alone) or not. A group that is not sees the whole catalog. A restricted group lists the
 * categories it may browse (customer_group_category), and the list can be empty: deleting the
 * one category it listed cascades on the list and leaves the flag, so the group then sees no
 * category at all, and no more than the products filed nowhere, instead of everything. Whatever
 * the list holds, it sorts every category in three:
 *
 * - open: the categories listed and their whole sub-trees;
 * - passing: the ancestors of the categories listed that are not open themselves. They stay
 *   on the menu, the breadcrumb and their own page, so that the open ones can be reached,
 *   but the products filed in them are shown only when an open category holds them too;
 * - closed: every other one, hidden altogether: out of the lists, 404 on its page and on its
 *   API item.
 *
 * The list of one group never opens nor closes anything to another group.
 *
 * What is read is kept for the request: the lists once, the tree once, and the sorting once
 * per group. A shop where no group is restricted reads no list: the existence of a restricted
 * group is part of the single activity statement of the request ({@see CatalogActivityProbe}), and no
 * criterion is added to any query. Only front readers ask: the back office and the admin API
 * are never narrowed.
 */
final class CategoryVisibility implements ResetInterface
{
    /** @var array<int, list<int>>|null ids of the categories each restricted group lists, by group id; a restricted group whose list is empty is there with an empty list */
    private ?array $categoryIdsByGroup = null;

    /** @var array<int, int>|null parent id by category id, read only when a group is restricted */
    private ?array $parentIdByCategory = null;

    /** @var array<int, array{open: list<int>, closed: list<int>}|null> how the catalog is sorted for each group, null when it sees everything */
    private array $accessByGroup = [];

    public function __construct(
        private readonly CustomerGroupResolver $customerGroupResolver,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        private readonly CatalogActivityProbe $catalogActivityProbe,
    ) {
    }

    /**
     * Whether any group is restricted to some categories (an empty list included): when none is, every visitor sees
     * the same catalog, and a cache keyed on the path alone is still sound.
     */
    public function hasRestrictions(): bool
    {
        return [] !== $this->restrictions();
    }

    /**
     * @return list<int> the categories the customer's group may not browse at all, neither open
     *                   nor passing; a visitor who is not signed in, or a guest, is asked for the default group
     */
    public function closedCategoryIdsFor(?Customer $customer): array
    {
        return $this->accessFor($customer)['closed'] ?? [];
    }

    /**
     * @return list<int> the same, for the visitor the request is made by
     */
    public function closedCategoryIds(): array
    {
        return $this->closedCategoryIdsFor($this->currentCustomerProvider->getCurrentCustomer());
    }

    /**
     * @return list<int>|null the categories whose products the customer's group may see, the
     *                        listed ones and their sub-trees; null when the group sees the whole catalog
     */
    public function openCategoryIdsFor(?Customer $customer): ?array
    {
        return $this->accessFor($customer)['open'] ?? null;
    }

    /**
     * Narrows a query on categories to the ones the current visitor may browse, the passing
     * ones included.
     *
     * @param string $categoryIdColumn the qualified column holding the category id in the query being narrowed
     */
    public function applyTo(ModelCriteria $query, string $categoryIdColumn): void
    {
        $closedCategoryIds = $this->closedCategoryIds();

        if ([] === $closedCategoryIds) {
            return;
        }

        // Integers read from the database: nothing is bound.
        $query->where(\sprintf('%s NOT IN (%s)', $categoryIdColumn, implode(', ', $closedCategoryIds)));
    }

    /**
     * The sub-categories of a category the current visitor may browse: the figure a loop shows
     * next to a category must not count the ones it hides.
     */
    public function countOpenChildren(int $parentId): int
    {
        $query = CategoryQuery::create()->filterByParent($parentId);

        $this->applyTo($query, CategoryTableMap::COL_ID);

        return $query->count();
    }

    public function reset(): void
    {
        $this->categoryIdsByGroup = null;
        $this->parentIdByCategory = null;
        $this->accessByGroup = [];
    }

    /**
     * @return array{open: list<int>, closed: list<int>}|null
     */
    private function accessFor(?Customer $customer): ?array
    {
        $restrictions = $this->restrictions();

        // Settled before the group is read: no list, no question about the visitor.
        if ([] === $restrictions) {
            return null;
        }

        $groupId = $this->customerGroupResolver->groupIdOf($customer);

        if (!\array_key_exists($groupId, $this->accessByGroup)) {
            $this->accessByGroup[$groupId] = isset($restrictions[$groupId]) ? $this->sort($restrictions[$groupId]) : null;
        }

        return $this->accessByGroup[$groupId];
    }

    /**
     * @return array<int, list<int>>
     */
    private function restrictions(): array
    {
        if (null !== $this->categoryIdsByGroup) {
            return $this->categoryIdsByGroup;
        }

        if (!$this->catalogActivityProbe->read()->hasCategoryRestrictions) {
            return $this->categoryIdsByGroup = [];
        }

        $categoryIdsByGroup = [];

        $restrictedGroupIds = CustomerGroupQuery::create()
            ->filterByRestrictsCategories(1)
            ->select('Id')
            ->find();

        foreach ($restrictedGroupIds as $restrictedGroupId) {
            $categoryIdsByGroup[(int) $restrictedGroupId] = [];
        }

        $rows = CustomerGroupCategoryQuery::create()
            ->select(['CustomerGroupId', 'CategoryId'])
            ->find();

        foreach ($rows as $row) {
            // A link of a group that is not flagged is no restriction: the flag is the writer's word.
            if (isset($categoryIdsByGroup[(int) $row['CustomerGroupId']])) {
                $categoryIdsByGroup[(int) $row['CustomerGroupId']][] = (int) $row['CategoryId'];
            }
        }

        // Kept only once read whole: a read that failed must not be remembered as "nothing is restricted".
        return $this->categoryIdsByGroup = $categoryIdsByGroup;
    }

    /**
     * @param list<int> $listedCategoryIds
     *
     * @return array{open: list<int>, closed: list<int>}
     */
    private function sort(array $listedCategoryIds): array
    {
        $parentIdByCategory = $this->parentIdByCategory ??= $this->readTree();

        $childIdsByParent = [];

        foreach ($parentIdByCategory as $categoryId => $parentId) {
            $childIdsByParent[$parentId][] = $categoryId;
        }

        // Open: the listed categories and everything below them.
        $open = [];
        $toVisit = $listedCategoryIds;

        while ([] !== $toVisit) {
            $categoryId = array_pop($toVisit);

            // A category reached twice means a loop in the tree, or a listed category below
            // another one: it is opened once and not walked again.
            if (isset($open[$categoryId])) {
                continue;
            }

            $open[$categoryId] = true;

            foreach ($childIdsByParent[$categoryId] ?? [] as $childId) {
                $toVisit[] = $childId;
            }
        }

        // Passing: the ancestors of the listed categories that are not open themselves.
        $passing = [];

        foreach ($listedCategoryIds as $categoryId) {
            $parentId = $parentIdByCategory[$categoryId] ?? 0;

            while (0 !== $parentId && !isset($open[$parentId]) && !isset($passing[$parentId])) {
                $passing[$parentId] = true;
                $parentId = $parentIdByCategory[$parentId] ?? 0;
            }
        }

        $closed = [];

        foreach (array_keys($parentIdByCategory) as $categoryId) {
            if (!isset($open[$categoryId]) && !isset($passing[$categoryId])) {
                $closed[] = $categoryId;
            }
        }

        $open = array_keys($open);
        sort($open);
        sort($closed);

        return ['open' => $open, 'closed' => $closed];
    }

    /**
     * @return array<int, int>
     */
    private function readTree(): array
    {
        $parentIdByCategory = [];

        foreach (CategoryQuery::create()->select(['Id', 'Parent'])->find() as $row) {
            $parentIdByCategory[(int) $row['Id']] = (int) $row['Parent'];
        }

        return $parentIdByCategory;
    }
}
