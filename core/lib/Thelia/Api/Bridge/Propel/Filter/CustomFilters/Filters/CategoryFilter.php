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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\CategoryQuery;

class CategoryFilter implements TheliaFilterInterface
{
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
        if (\is_string($valueSearched)) {
            $valueSearched = explode(',', $valueSearched);
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

            $categoriesWithDepth = $this->filterService->getCategoriesRecursively(categoryId: $categoryId, maxDepth: $depth);

            if ([] === $categoriesWithDepth) {
                return [];
            }

            foreach ($categoriesWithDepth as $depthIndex => $categories) {
                foreach ($categories as $category) {
                    $value[] =
                        (new FilterValue())
                            ->setMainTitle($mainCategory->setLocale($locale)->getTitle())
                            ->setMainId($mainCategory->getId())
                            ->setId($category->getId())
                            ->setDepth($depthIndex)
                            ->setTitle($category->setLocale($locale)->getTitle());
                }
            }
        }

        return $value;
    }
}
