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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\CatalogRestrictionQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CatalogRestrictionTableMap;
use Thelia\Model\ProductCategoryQuery;

/**
 * The catalog restrictions that apply to a customer, in one place, so that the product
 * rule, the categories and the brands can never disagree.
 *
 * A customer is bound by the rules of their group (a visitor, a guest and a console
 * command are the default group) and by their own, taken together. Whatever the rules
 * say, a product is open when an allow rule opens it (the product, its brand, or one of its
 * categories, a category standing for its whole sub-tree), unless the customer has no allow
 * rule at all, in which case everything is open; and closed when a deny rule closes it (the
 * product, its brand, or one of its categories), whatever an allow rule says. A product filed
 * nowhere is opened by no category: only an allow rule on the product or on its brand opens
 * it, and any allow rule closes it otherwise.
 *
 * The rules of a customer are read in one statement and kept for the request, as are the
 * categories that follow from them; the tree of the categories is read once, and only when a
 * rule targets a category. A shop with no rule reads nothing: the existence of a rule is
 * known from the single statement the front already runs ({@see CatalogActivityProbe}).
 * Only front readers ask: the back office and the admin API are never narrowed.
 */
final class CatalogRestrictionResolver implements ResetInterface
{
    /** @var array<string, CatalogRestrictionScope|null> by "group id:customer id" */
    private array $scopeByVisitor = [];

    /** @var array<string, list<int>> */
    private array $closedCategoryIdsByVisitor = [];

    /** @var array<int, int>|null parent id by category id */
    private ?array $parentIdByCategory = null;

    public function __construct(
        private readonly CustomerGroupResolver $customerGroupResolver,
        private readonly CatalogActivityProbe $catalogActivityProbe,
    ) {
    }

    /**
     * @return CatalogRestrictionScope|null null when no rule binds the customer
     */
    public function scopeFor(?Customer $customer): ?CatalogRestrictionScope
    {
        [$groupId, $customerId, $key] = $this->visitorOf($customer);

        if (!\array_key_exists($key, $this->scopeByVisitor)) {
            $this->scopeByVisitor[$key] = $this->read($groupId, $customerId);
        }

        return $this->scopeByVisitor[$key];
    }

    /**
     * The categories the rules close to the customer, in the sense of the catalog tree: the
     * ones a deny rule targets with their sub-trees, and, when the customer has an allow list,
     * every category it does not open. A category is open when an allow rule targets it or one
     * of its ancestors, or when it files a product an allow rule opens; the ancestors of an open
     * category stay passing, as they do for the categories a customer group lists.
     *
     * @return list<int>
     */
    public function closedCategoryIdsFor(?Customer $customer): array
    {
        $scope = $this->scopeFor($customer);

        if (null === $scope) {
            return [];
        }

        [, , $key] = $this->visitorOf($customer);

        return $this->closedCategoryIdsByVisitor[$key] ??= $this->closedCategories($scope);
    }

    /**
     * Narrows a query to the brands the customer may see: a denied brand is out and, once the
     * customer has an allow list, a brand stays when a rule lists it or when it carries a product
     * the customer may see.
     *
     * @param string $brandIdColumn the qualified column holding the brand id in the query being narrowed
     */
    public function applyToBrands(ModelCriteria $query, string $brandIdColumn, ?Customer $customer): void
    {
        $scope = $this->scopeFor($customer);

        if (null === $scope) {
            return;
        }

        $clause = CatalogRestrictionClauses::visibleBrand($scope, $brandIdColumn);

        if (null !== $clause) {
            $query->where($clause);
        }
    }

    public function reset(): void
    {
        $this->scopeByVisitor = [];
        $this->closedCategoryIdsByVisitor = [];
        $this->parentIdByCategory = null;
    }

    /**
     * @return array{int, int|null, string}
     */
    private function visitorOf(?Customer $customer): array
    {
        $groupId = $this->customerGroupResolver->groupIdOf($customer);
        $customerId = null === $customer || $customer->isNew() || 0 === (int) $customer->getId() ? null : (int) $customer->getId();

        return [$groupId, $customerId, $groupId.':'.($customerId ?? 0)];
    }

    private function read(int $groupId, ?int $customerId): ?CatalogRestrictionScope
    {
        // The probe is read once by the front for everything it asks: a shop without a rule
        // never reads the table.
        if (!$this->catalogActivityProbe->read()->hasCatalogRestrictions) {
            return null;
        }

        $query = CatalogRestrictionQuery::create()
            ->condition('ofTheGroup', CatalogRestrictionTableMap::COL_CUSTOMER_GROUP_ID.' = ?', $groupId);

        if (null === $customerId) {
            $query->where(['ofTheGroup']);
        } else {
            $query
                ->condition('ofTheCustomer', CatalogRestrictionTableMap::COL_CUSTOMER_ID.' = ?', $customerId)
                ->where(['ofTheGroup', 'ofTheCustomer'], Criteria::LOGICAL_OR);
        }

        $rows = $query->select(['CategoryId', 'BrandId', 'ProductId', 'Mode'])->find();

        $categoryIds = [CatalogRestrictionMode::Allow->value => [], CatalogRestrictionMode::Deny->value => []];
        $brandIds = $categoryIds;
        $productIds = $categoryIds;

        foreach ($rows as $row) {
            $mode = (string) $row['Mode'];

            // A mode this class does not know is no rule: nothing is opened nor closed on its word.
            if (!isset($categoryIds[$mode])) {
                continue;
            }

            if (null !== $row['CategoryId']) {
                $categoryIds[$mode][] = (int) $row['CategoryId'];
            } elseif (null !== $row['BrandId']) {
                $brandIds[$mode][] = (int) $row['BrandId'];
            } elseif (null !== $row['ProductId']) {
                $productIds[$mode][] = (int) $row['ProductId'];
            }
        }

        $scope = new CatalogRestrictionScope(
            allowedCategoryIds: $this->withSubTrees($categoryIds[CatalogRestrictionMode::Allow->value]),
            allowedBrandIds: $this->unique($brandIds[CatalogRestrictionMode::Allow->value]),
            allowedProductIds: $this->unique($productIds[CatalogRestrictionMode::Allow->value]),
            deniedCategoryIds: $this->withSubTrees($categoryIds[CatalogRestrictionMode::Deny->value]),
            deniedBrandIds: $this->unique($brandIds[CatalogRestrictionMode::Deny->value]),
            deniedProductIds: $this->unique($productIds[CatalogRestrictionMode::Deny->value]),
        );

        return $scope->isEmpty() ? null : $scope;
    }

    /**
     * @return list<int>
     */
    private function closedCategories(CatalogRestrictionScope $scope): array
    {
        $parentIdByCategory = $this->tree();
        $closed = array_fill_keys($scope->deniedCategoryIds, true);

        if ($scope->hasAllowList()) {
            $open = array_fill_keys([...$scope->allowedCategoryIds, ...$this->categoryIdsFilingAllowedProducts($scope)], true);

            $passing = [];

            foreach (array_keys($open) as $categoryId) {
                $parentId = $parentIdByCategory[$categoryId] ?? 0;

                while (0 !== $parentId && !isset($open[$parentId]) && !isset($passing[$parentId])) {
                    $passing[$parentId] = true;
                    $parentId = $parentIdByCategory[$parentId] ?? 0;
                }
            }

            foreach (array_keys($parentIdByCategory) as $categoryId) {
                if (!isset($open[$categoryId]) && !isset($passing[$categoryId])) {
                    $closed[$categoryId] = true;
                }
            }
        }

        $closedCategoryIds = array_keys($closed);
        sort($closedCategoryIds);

        return $closedCategoryIds;
    }

    /**
     * @return list<int> the categories that file a product an allow rule opens by itself or through its brand
     */
    private function categoryIdsFilingAllowedProducts(CatalogRestrictionScope $scope): array
    {
        $categoryIds = [];

        if ([] !== $scope->allowedProductIds) {
            foreach (ProductCategoryQuery::create()->filterByProductId($scope->allowedProductIds, Criteria::IN)->select('CategoryId')->find() as $categoryId) {
                $categoryIds[] = (int) $categoryId;
            }
        }

        if ([] !== $scope->allowedBrandIds) {
            $query = ProductCategoryQuery::create()
                ->useProductQuery()
                    ->filterByBrandId($scope->allowedBrandIds, Criteria::IN)
                ->endUse()
                ->select('CategoryId')
                ->distinct();

            foreach ($query->find() as $categoryId) {
                $categoryIds[] = (int) $categoryId;
            }
        }

        return $categoryIds;
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<int> the categories and everything below them
     */
    private function withSubTrees(array $categoryIds): array
    {
        if ([] === $categoryIds) {
            return [];
        }

        $childIdsByParent = [];

        foreach ($this->tree() as $categoryId => $parentId) {
            $childIdsByParent[$parentId][] = $categoryId;
        }

        $reached = [];
        $toVisit = $categoryIds;

        while ([] !== $toVisit) {
            $categoryId = array_pop($toVisit);

            // A category reached twice is a rule below another one, or a loop in the tree.
            if (isset($reached[$categoryId])) {
                continue;
            }

            $reached[$categoryId] = true;

            foreach ($childIdsByParent[$categoryId] ?? [] as $childId) {
                $toVisit[] = $childId;
            }
        }

        $reachedIds = array_keys($reached);
        sort($reachedIds);

        return $reachedIds;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function unique(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function tree(): array
    {
        if (null === $this->parentIdByCategory) {
            $parentIdByCategory = [];

            foreach (CategoryQuery::create()->select(['Id', 'Parent'])->find() as $row) {
                $parentIdByCategory[(int) $row['Id']] = (int) $row['Parent'];
            }

            $this->parentIdByCategory = $parentIdByCategory;
        }

        return $this->parentIdByCategory;
    }
}
