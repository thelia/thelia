<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class FeatureFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['features'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->useFeatureProductQuery()->filterByFeatureId($value)->endUse();
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        $value = [];
        foreach ($activeRecord->getFeatureProducts() as $featureProduct) {
            $value[] =
                [
                    'id' => $featureProduct->getFeature()->getId(),
                    'title' => $featureProduct->getFeature()->getTitle(),
                ]
            ;
        }
        return $value;
    }
}
