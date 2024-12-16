<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class FeatureAvFilter implements TheliaFilterInterface,TheliaChoiceFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['featureAvs'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useFeatureProductQuery()->filterByFeatureAvId($value)->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getFeatureProductsJoinFeatureAv() as $featureProduct) {
            $value[] =
                [
                    'id' => $featureProduct->getFeatureAv()->getId(),
                    'title' => $featureProduct->getFeatureAv()->getTitle(),
                ]
            ;
        }
        return $value;
    }

    public function getChoiceFilterType(ActiveRecordInterface $activeRecord): ActiveRecordInterface
    {
        return $activeRecord->getFeatureProductsJoinFeatureAv()->getFirst()->getFeature();
    }
}
