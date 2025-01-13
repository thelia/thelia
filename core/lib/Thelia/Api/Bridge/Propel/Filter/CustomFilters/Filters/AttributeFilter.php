<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class AttributeFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['attributes'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useProductSaleElementsQuery()->filterByIsDefault(1)->useAttributeCombinationQuery()->filterByAttributeId($value)->endUse()->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord,string $locale): ?array
    {
        if (empty($activeRecord->getDefaultSaleElements()?->getAttributeCombinationsJoinAttribute())){
            return null;
        }
        $value = [];
        foreach ($activeRecord->getDefaultSaleElements()->getAttributeCombinationsJoinAttribute() as $attribute) {
            $value[] =
                [
                    'id' => $attribute->getAttributeId(),
                    'title' => $attribute->getAttribute()->setLocale($locale)->getTitle(),
                ]
            ;
        }
        return $value;
    }
}
