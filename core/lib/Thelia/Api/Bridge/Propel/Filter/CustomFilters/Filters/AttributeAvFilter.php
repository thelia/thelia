<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class AttributeAvFilter implements TheliaFilterInterface,TheliaChoiceFilterInterface
{

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['attributesAvs'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useProductSaleElementsQuery()->filterByIsDefault(1)->useAttributeCombinationQuery()->filterByAttributeAvId($value)->endUse()->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getDefaultSaleElements()->getAttributeCombinationsJoinAttributeAv() as $attributeAv) {
            $value[] =
                [
                    'id' => $attributeAv->getAttributeAvId(),
                    'title' => $attributeAv->getAttributeAv()->getTitle(),
                ]
            ;
        }
        return $value;
    }

    public function getChoiceFilterType(ActiveRecordInterface $activeRecord): ActiveRecordInterface
    {
        return $activeRecord->getDefaultSaleElements()->getAttributeCombinationsJoinAttributeAv()->getFirst()->getAttribute();
    }
}
