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
use Thelia\Model\Lang;
use Thelia\Model\Map\FeatureProductTableMap;

class FeatureAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;
    use SelectedValuesTrait;

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['feature'];
    }

    /**
     * Each feature of the selection is read in its own mode: checked values, or `min`/`max`
     * bounds for a feature rendered as a slider. Checked values widen the match inside a feature
     * and narrow it across features; a bound narrows on its own feature only.
     */
    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        ['checked' => $checked, 'bounded' => $bounded] = $this->splitSelectedValues($value);

        $featureAvIds = array_values(array_unique(array_merge(...array_values($checked ?: [[]]))));

        if ($featureAvIds !== []) {
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
        }

        foreach ($bounded as $featureId => $bounds) {
            // A distinct alias per bounded feature: two sliders must each read their own join,
            // not share the one the checked values use.
            $alias = 'bounded_feature_'.preg_replace('/\D/', '', (string) $featureId);

            $featureProductQuery = $query
                ->useFeatureProductQuery($alias)
                ->filterByFeatureId((int) $featureId)
                ->useFeatureAvQuery($alias.'_av')
                ->useI18nQuery(Lang::getDefaultLanguage()->getLocale(), $alias.'_av_i18n');

            foreach ($bounds as $type => $limit) {
                $operator = $type === 'min' ? Criteria::GREATER_EQUAL : Criteria::LESS_EQUAL;
                $featureProductQuery->where(\sprintf('CAST(%s_av_i18n.title AS UNSIGNED) %s ?', $alias, $operator), (int) $limit);
            }

            $featureProductQuery
                ->endUse()
                ->endUse()
                ->endUse();
        }
    }

    /**
     * The feature availability identifiers held by a [featureId => values] selection, whatever
     * the depth the query string nested them at.
     *
     * @return array<int, int|string>
     */
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
     * One query for the distinct (feature, value) pairs the set holds with the number of
     * products carrying each, then the features and values themselves with their translations
     * — bounded by the shop's taxonomy, not by the number of products.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $pairs = FeatureProductQuery::create()
            ->filterByProductId($resourceIds, Criteria::IN)
            ->filterByFeatureAvId(null, Criteria::ISNOTNULL)
            ->withColumn('COUNT(DISTINCT '.FeatureProductTableMap::COL_PRODUCT_ID.')', 'ProductCount')
            ->select(['FeatureId', 'FeatureAvId', 'ProductCount'])
            ->groupBy('FeatureId')
            ->addGroupByColumn(FeatureProductTableMap::COL_FEATURE_AV_ID)
            ->find()
            ->getData();

        if ($pairs === []) {
            return [];
        }

        $features = FeatureQuery::create()
            ->filterById(array_column($pairs, 'FeatureId'), Criteria::IN)
            ->joinWithI18n($locale)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        $featureAvs = FeatureAvQuery::create()
            ->filterById(array_column($pairs, 'FeatureAvId'), Criteria::IN)
            ->joinWithI18n($locale)
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
                    ->setTitle($this->localizedTitle($featureAv, $locale))
                    ->setCount((int) $pair['ProductCount']);
        }

        return $values;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Feature();
    }
}
