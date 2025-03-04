<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\Map\FeatureProductTableMap;

class FeatureAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['feature'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        if (!\is_array($value)) {
            $value = [$value];
        }
        $count = FeatureAvQuery::create()
            ->filterById($value, Criteria::IN)
            ->withColumn('COUNT(DISTINCT feature_id)', 'distinct_feature_count')
            ->select(['distinct_feature_count'])
            ->findOne();

        $query
            ->useFeatureProductQuery()
            ->filterByFeatureAvId($value, Criteria::IN)
            ->endUse()
            ->groupBy(FeatureProductTableMap::COL_PRODUCT_ID)
            ->having('COUNT(DISTINCT '.FeatureProductTableMap::COL_FEATURE_ID.') = ?', $count);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (empty($activeRecord->getFeatureProductsJoinFeatureAv())) {
            return null;
        }
        $value = [];
        foreach ($activeRecord->getFeatureProductsJoinFeatureAv() as $featureProduct) {
            $value[] =
                [
                    'mainTitle' => $featureProduct->getFeature()->setLocale($locale)->getTitle(),
                    'mainId' => $featureProduct->getFeature()->getId(),
                    'id' => $featureProduct->getFeatureAv()->getId(),
                    'title' => $featureProduct->getFeatureAv()->setLocale($locale)->getTitle(),
                ]
            ;
        }

        return $value;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Feature();
    }
}
