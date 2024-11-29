<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;

class FeatureAvFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public function getFilterName(): array
    {
        return ['featureAvs'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useFeatureProductQuery()->filterByFeatureAv($value)->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getFeatureProductsJoinFeatureAv() as $featureProduct) {
            $value[] =
                [
                    'id' => $featureProduct->getFeatureAv()->getId(),
                    'title' => $featureProduct->getFeatureAv()->getTitle(),
                    'value' => 1
                ]
            ;
        }
        return $value;
    }
}
