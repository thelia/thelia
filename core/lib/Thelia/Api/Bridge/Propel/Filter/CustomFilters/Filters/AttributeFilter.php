<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

class AttributeFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public function getFilterName(): array
    {
        return ['attributes'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useProductSaleElementsQuery()->filterByIsDefault(1)->useAttributeCombinationQuery()->filterByAttributeId($value)->endUse()->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getDefaultSaleElements()->getAttributeCombinationsJoinAttribute() as $attribute) {
            $value[] =
                [
                    'id' => $attribute->getAttributeId(),
                    'title' => $attribute->getAttribute()->getTitle(),
                    'value' => 1
                ]
            ;
        }
        return $value;
    }
}
