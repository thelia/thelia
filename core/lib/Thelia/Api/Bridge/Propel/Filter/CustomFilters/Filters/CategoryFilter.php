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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\CategoryQuery;

class CategoryFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;

    public const CATEGORY_DEPTH_NAME = 'category_depth';

    public function __construct(private readonly FilterService $filterService)
    {
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        foreach ($value as $id => $childValue) {
            foreach ($childValue as $type => $categoryId) {
                if (!\is_array($categoryId)) {
                    $categoryId = [$categoryId];
                }

                if ($categoryDepth) {
                    $categories = $this->filterService->getCategoriesRecursively($categoryId, $categoryDepth);

                    foreach ($categories as $categoryList) {
                        foreach ($categoryList as $category) {
                            $categoryId[] = $category->getId();
                        }
                    }
                }

                $query->useProductCategoryQuery()->filterByCategoryId($categoryId)->endUse();
            }
        }
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['category'];
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        return $this->browsedCategories($locale, $valueSearched, $depth);
    }

    /**
     * The categories offered as facets are those below the browsed one, which the
     * products of the set do not take part in: the set only has to be non-empty.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return $this->browsedCategories($locale, $valueSearched, $depth);
    }

    private function browsedCategories(string $locale, $valueSearched, ?int $depth): array
    {
        if (\is_string($valueSearched) || \is_int($valueSearched)) {
            $valueSearched = explode(',', (string) $valueSearched);
        }

        if (empty($valueSearched)) {
            return [];
        }

        $value = [];

        foreach ($valueSearched as $categoryId) {
            $mainCategory = CategoryQuery::create()->findOneById($categoryId);

            if (!$mainCategory) {
                continue;
            }

            $categoriesWithDepth = $this->filterService->getCategoriesRecursively(categoryId: $categoryId, maxDepth: $depth ?? 1);

            if ([] === $categoriesWithDepth) {
                return [];
            }

            foreach ($categoriesWithDepth as $depthIndex => $categories) {
                foreach ($categories as $category) {
                    $value[] =
                        (new FilterValue())
                            ->setMainTitle($this->localizedTitle($mainCategory, $locale))
                            ->setMainId($mainCategory->getId())
                            ->setId($category->getId())
                            ->setDepth($depthIndex)
                            ->setTitle($this->localizedTitle($category, $locale));
                }
            }
        }

        return $value;
    }
}
