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
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;

class AttributeAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;
    use SelectedValuesTrait;

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['attribute'];
    }

    /**
     * Each attribute of the selection is read in its own mode: checked values, or `min`/`max`
     * bounds for an attribute rendered as a slider. A product matches when one of its sale
     * elements carries one checked value of every checked attribute: "S" or "M" widens, "S" and
     * "blue" narrows to the variants that are both.
     */
    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        ['checked' => $checked, 'bounded' => $bounded] = $this->splitSelectedValues($value);

        $attributeAvIds = array_values(array_unique(array_merge(...array_values($checked ?: [[]]))));

        if ($attributeAvIds !== []) {
            $count = AttributeAvQuery::create()
                ->filterById($attributeAvIds, Criteria::IN)
                ->withColumn('COUNT(DISTINCT attribute_id)', 'distinct_attribute_count')
                ->select(['distinct_attribute_count'])
                ->findOne();

            // One IN for every checked value, and the HAVING asks a single sale element to hold
            // as many distinct attributes as were checked: values of one attribute count as one.
            $query
                ->useProductSaleElementsQuery()
                    ->useAttributeCombinationQuery()
                        ->filterByAttributeAvId($attributeAvIds, Criteria::IN)
                    ->endUse()
                ->endUse()
                ->groupBy(ProductSaleElementsTableMap::COL_ID)
                ->having('COUNT(DISTINCT '.AttributeCombinationTableMap::COL_ATTRIBUTE_ID.') = ?', $count);
        }

        foreach ($bounded as $attributeId => $bounds) {
            $alias = 'bounded_attribute_'.preg_replace('/\D/', '', (string) $attributeId);

            $combinationQuery = $query
                ->useProductSaleElementsQuery($alias.'_pse')
                ->useAttributeCombinationQuery($alias)
                ->filterByAttributeId((int) $attributeId)
                ->useAttributeAvQuery($alias.'_av')
                ->useI18nQuery(Lang::getDefaultLanguage()->getLocale(), $alias.'_av_i18n');

            foreach ($bounds as $type => $limit) {
                $operator = $type === 'min' ? Criteria::GREATER_EQUAL : Criteria::LESS_EQUAL;
                $combinationQuery->where(\sprintf('CAST(%s_av_i18n.title AS UNSIGNED) %s ?', $alias, $operator), (int) $limit);
            }

            $combinationQuery
                ->endUse()
                ->endUse()
                ->endUse()
                ->endUse();
        }
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        $productSaleElementss = $activeRecord->getProductSaleElementss();

        if (empty($productSaleElementss)) {
            return null;
        }

        $value = [];

        foreach ($productSaleElementss as $productSaleElements) {
            foreach ($productSaleElements->getAttributeCombinationsJoinAttributeAv() as $attributeAv) {
                $value[] =
                    (new FilterValue())
                        ->setMainTitle($this->localizedTitle($attributeAv->getAttribute(), $locale))
                        ->setMainId($attributeAv->getAttribute()->getId())
                        ->setId($attributeAv->getAttributeAvId())
                        ->setTitle($this->localizedTitle($attributeAv->getAttributeAv(), $locale));
            }
        }

        return $value;
    }

    /**
     * The attribute values a product set offers, each with the number of products having a
     * sale element that carries it, are a GROUP BY over the combinations of its sale elements:
     * one query instead of one per sale element.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $pairs = AttributeCombinationQuery::create()
            ->useProductSaleElementsQuery()
                ->filterByProductId($resourceIds, Criteria::IN)
            ->endUse()
            ->withColumn('COUNT(DISTINCT '.ProductSaleElementsTableMap::COL_PRODUCT_ID.')', 'ProductCount')
            ->select(['AttributeId', 'AttributeAvId', 'ProductCount'])
            ->groupBy('AttributeId')
            ->addGroupByColumn(AttributeCombinationTableMap::COL_ATTRIBUTE_AV_ID)
            ->find()
            ->getData();

        if ($pairs === []) {
            return [];
        }

        $attributes = AttributeQuery::create()
            ->filterById(array_column($pairs, 'AttributeId'), Criteria::IN)
            ->joinWithI18n($locale)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        $attributeAvs = AttributeAvQuery::create()
            ->filterById(array_column($pairs, 'AttributeAvId'), Criteria::IN)
            ->joinWithI18n($locale)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        // Both collections come back ordered by position: their order is the order
        // the facets are presented in.
        $attributeRank = array_flip(array_keys($attributes));
        $attributeAvRank = array_flip(array_keys($attributeAvs));

        usort(
            $pairs,
            static fn (array $left, array $right): int => [$attributeRank[$left['AttributeId']] ?? \PHP_INT_MAX, $attributeAvRank[$left['AttributeAvId']] ?? \PHP_INT_MAX]
                <=> [$attributeRank[$right['AttributeId']] ?? \PHP_INT_MAX, $attributeAvRank[$right['AttributeAvId']] ?? \PHP_INT_MAX],
        );

        $values = [];

        foreach ($pairs as $pair) {
            $attribute = $attributes[$pair['AttributeId']] ?? null;
            $attributeAv = $attributeAvs[$pair['AttributeAvId']] ?? null;

            if (null === $attribute || null === $attributeAv) {
                continue;
            }

            $values[] =
                (new FilterValue())
                    ->setMainTitle($this->localizedTitle($attribute, $locale))
                    ->setMainId((int) $pair['AttributeId'])
                    ->setId((int) $pair['AttributeAvId'])
                    ->setTitle($this->localizedTitle($attributeAv, $locale))
                    ->setCount((int) $pair['ProductCount']);
        }

        return $values;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Attribute();
    }
}
