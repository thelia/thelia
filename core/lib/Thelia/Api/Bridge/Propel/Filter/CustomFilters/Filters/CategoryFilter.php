<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class CategoryFilter implements TheliaFilterInterface
{
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
        return ['categories','category'];
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getCategories() as $category) {
            $value[] =
                [
                    'id' => $category->getId(),
                    'title' => $category->getTitle(),
                ]
            ;
        }
        return $value;
    }
}
