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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\PropertyInfo\Type;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;

class DepthProductFilter extends AbstractFilter
{
    public function __construct(
        private readonly FilterService $filterService,
    ) {
        parent::__construct();
    }

    private const DEPTH = 'depth';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== self::DEPTH) {
            return;
        }

        $categoryId = $context['filters']['productCategories.category.id'] ?? null;
        if (!$categoryId || !is_numeric($categoryId) || !$value || !is_numeric($value)) {
            return;
        }

        $categoriesWithDepth = $this->filterService->getCategoriesRecursively(categoryId: $categoryId, maxDepth: (int) $value);
        $idCategories = [];
        foreach ($categoriesWithDepth as $categories) {
            foreach ($categories as $category) {
                $idCategories[] = $category->getId();
            }
        }

        $query
            ->_or()
            ->useProductCategoryQuery()
            ->filterByCategoryId($idCategories)
            ->endUse()
            ->groupById();
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'depth' => [
                'property' => 'depth',
                'type' => Type::BUILTIN_TYPE_INT,
                'required' => false,
                'description' => 'Defines the search depth for child categories. This filter can only be used within the category filter(productCategories.category.id).',
            ],
        ];
    }
}
