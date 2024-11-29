<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

class AttributeAvFilter implements TheliaFilterInterface
{

    public function getResourceType(): array
    {
        return ['products'];
    }

    public function getFilterName(): array
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
                    'value' => 1
                ]
            ;
        }
        return $value;
    }
}
