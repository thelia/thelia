<?php

declare(strict_types=1);

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
use Thelia\Api\Resource\FilterValue;
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

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        if (!$isMinOrMaxFilter) {
            foreach ($value as $featureId => $childValue) {
                foreach ($childValue as $type => $featureAvId) {
                    $count = FeatureAvQuery::create()
                        ->filterById($featureAvId, Criteria::IN)
                        ->withColumn('COUNT(DISTINCT feature_id)', 'distinct_feature_count')
                        ->select(['distinct_feature_count'])
                        ->findOne();

                    $query
                        ->useFeatureProductQuery()
                        ->filterByFeatureAvId($featureAvId, Criteria::IN)
                        ->endUse()
                        ->groupBy(FeatureProductTableMap::COL_PRODUCT_ID)
                        ->having('COUNT(DISTINCT '.FeatureProductTableMap::COL_FEATURE_ID.') = ?', $count);
                }
            }

            return;
        }
        foreach ($value as $featureId => $childValue) {
            foreach ($childValue as $type => $limit) {
                $operator = $type === 'min' ? Criteria::GREATER_EQUAL : Criteria::LESS_EQUAL;

                $query
                    ->useFeatureProductQuery()
                    ->filterByFeatureId($featureId)
                        ->useFeatureAvQuery()
                            ->useI18nQuery()
                                ->where(\sprintf('CAST(feature_av_i18n.title AS UNSIGNED) %s ?', $operator), (int) $limit)
                            ->endUse()
                        ->endUse()
                    ->endUse();
            }
        }
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (empty($activeRecord->getFeatureProductsJoinFeatureAv())) {
            return null;
        }

        $value = [];

        foreach ($activeRecord->getFeatureProductsJoinFeatureAv() as $featureProduct) {
            $value[] =
                (new FilterValue())
                    ->setMainTitle($featureProduct->getFeature()->setLocale($locale)->getTitle())
                    ->setMainId($featureProduct->getFeature()->getId())
                    ->setId($featureProduct->getFeatureAv()->getId())
                    ->setTitle($featureProduct->getFeatureAv()->setLocale($locale)->getTitle());
        }

        return $value;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Feature();
    }
}
