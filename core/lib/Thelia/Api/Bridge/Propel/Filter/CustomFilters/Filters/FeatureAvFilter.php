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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Feature;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\Map\FeatureProductTableMap;

class FeatureAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;

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
            $featureAvIds = $this->flattenSelectedValues($value);

            if ($featureAvIds === []) {
                return;
            }

            // Every identifier selected across every feature goes into a single IN, and the
            // HAVING then asks a product to carry as many distinct features as were selected.
            // Two values of the same feature widen the match (they count as one feature), values
            // of two different features narrow it. Applying one IN per value instead would put
            // two mutually exclusive conditions on the same join and match nothing.
            $count = FeatureAvQuery::create()
                ->filterById($featureAvIds, Criteria::IN)
                ->withColumn('COUNT(DISTINCT feature_id)', 'distinct_feature_count')
                ->select(['distinct_feature_count'])
                ->findOne();

            $query
                ->useFeatureProductQuery()
                ->filterByFeatureAvId($featureAvIds, Criteria::IN)
                ->endUse()
                ->groupBy(FeatureProductTableMap::COL_PRODUCT_ID)
                ->having('COUNT(DISTINCT '.FeatureProductTableMap::COL_FEATURE_ID.') = ?', $count);

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

    /**
     * The feature availability identifiers held by a [featureId => values] selection, whatever
     * the depth the query string nested them at.
     *
     * @return array<int, int|string>
     */
    private function flattenSelectedValues(mixed $value): array
    {
        $featureAvIds = [];

        foreach ((array) $value as $childValue) {
            foreach ((array) $childValue as $featureAvId) {
                if (\is_array($featureAvId) || $featureAvId === null || $featureAvId === '') {
                    continue;
                }

                $featureAvIds[] = $featureAvId;
            }
        }

        return array_values(array_unique($featureAvIds));
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (empty($activeRecord->getFeatureProductsJoinFeatureAv())) {
            return null;
        }

        $value = [];

        foreach ($activeRecord->getFeatureProductsJoinFeatureAv() as $featureProduct) {
            if (null === $featureProduct->getFeatureAv()) {
                continue;
            }

            $value[] =
                (new FilterValue())
                ->setMainTitle($this->localizedTitle($featureProduct->getFeature(), $locale))
                ->setMainId($featureProduct->getFeature()->getId())
                ->setId($featureProduct->getFeatureAv()->getId())
                ->setTitle($this->localizedTitle($featureProduct->getFeatureAv(), $locale));
        }

        return $value;
    }

    /**
     * One query for the distinct (feature, value) pairs the set holds, then the
     * features and values themselves — bounded by the shop's taxonomy, not by the
     * number of products.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $pairs = FeatureProductQuery::create()
            ->filterByProductId($resourceIds, Criteria::IN)
            ->filterByFeatureAvId(null, Criteria::ISNOTNULL)
            ->select(['FeatureId', 'FeatureAvId'])
            ->distinct()
            ->find()
            ->getData();

        if ($pairs === []) {
            return [];
        }

        $features = FeatureQuery::create()
            ->filterById(array_column($pairs, 'FeatureId'), Criteria::IN)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        $featureAvs = FeatureAvQuery::create()
            ->filterById(array_column($pairs, 'FeatureAvId'), Criteria::IN)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        // Both collections come back ordered by position: their order is the order
        // the facets are presented in.
        $featureRank = array_flip(array_keys($features));
        $featureAvRank = array_flip(array_keys($featureAvs));

        usort(
            $pairs,
            static fn (array $left, array $right): int => [$featureRank[$left['FeatureId']] ?? \PHP_INT_MAX, $featureAvRank[$left['FeatureAvId']] ?? \PHP_INT_MAX]
                <=> [$featureRank[$right['FeatureId']] ?? \PHP_INT_MAX, $featureAvRank[$right['FeatureAvId']] ?? \PHP_INT_MAX],
        );

        $values = [];

        foreach ($pairs as $pair) {
            $feature = $features[$pair['FeatureId']] ?? null;
            $featureAv = $featureAvs[$pair['FeatureAvId']] ?? null;

            if (null === $feature || null === $featureAv) {
                continue;
            }

            $values[] =
                (new FilterValue())
                    ->setMainTitle($this->localizedTitle($feature, $locale))
                    ->setMainId((int) $pair['FeatureId'])
                    ->setId((int) $pair['FeatureAvId'])
                    ->setTitle($this->localizedTitle($featureAv, $locale));
        }

        return $values;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Feature();
    }
}
