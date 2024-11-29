<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;

class FeatureFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public function getFilterName(): array
    {
        return ['features'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useFeatureProductQuery()->filterByFeature($value)->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getFeatureProducts() as $featureProduct) {
            $value[] =
                [
                    'id' => $featureProduct->getFeature()->getId(),
                    'title' => $featureProduct->getFeature()->getTitle(),
                    'value' => 1
                ]
            ;
        }
        return $value;
    }
}
