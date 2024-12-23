<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Model\Brand;

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

    public function getValue(ActiveRecordInterface $activeRecord,string $locale): ?array
    {
        $brand = $activeRecord->getBrand();
        if (!$brand instanceof Brand){
            return null;
        }
        return [
            [
                'id' => $brand->getId(),
                'title' => $brand->setLocale($locale)->getTitle(),
                'value' => $brand->getId()
            ]
        ];
    }
}
