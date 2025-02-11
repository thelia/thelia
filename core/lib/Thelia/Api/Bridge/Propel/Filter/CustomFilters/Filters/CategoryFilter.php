<?php

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
use Thelia\Model\CategoryQuery;

class CategoryFilter implements TheliaFilterInterface
{
    public const CATEGORY_DEPTH_NAME = 'category_depth';

    public function __construct(private readonly FilterService $filterService)
    {
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useProductCategoryQuery()->filterByCategoryId($value)->endUse();
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
            if (empty($categoriesWithDepth)) {
                return [];
            }
            foreach ($categoriesWithDepth as $depthIndex => $categories) {
                foreach ($categories as $category) {
                    $value[] =
                        [
                            'mainTitle' => $mainCategory->setLocale($locale)->getTitle(),
                            'mainId' => $mainCategory->getId(),
                            'id' => $category->getId(),
                            'depth' => $depthIndex,
                            'title' => $category->setLocale($locale)->getTitle(),
                        ]
                    ;
                }
            }
        }

        return $value;
    }
}
