<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class BrandFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['brands','brand'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->filterByBrandId($value);
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        return [
            [
                'id' => $activeRecord->getBrand()->getId(),
                'title' => $activeRecord->getBrand()->getTitle(),
                'value' => $activeRecord->getBrand()->getId()
            ]
        ];
    }
}
