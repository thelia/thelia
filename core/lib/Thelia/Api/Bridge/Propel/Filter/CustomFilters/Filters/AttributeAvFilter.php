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

class AttributeAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['attribute'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        $rawAttributes = [];
        foreach ($value as $attributeId => $childValue) {
            foreach ($childValue as $type => $raw) {
                if (!$isMinOrMaxFilter) {
                    $rawAttributes[] = $raw;
                    continue;
                }
                $query = $query
                    ->useProductSaleElementsQuery()
                    ->useAttributeCombinationQuery();

                if ($isMinOrMaxFilter) {
                    $operator = $type === 'min' ? Criteria::GREATER_EQUAL : Criteria::LESS_EQUAL;

                    $query = $query
                        ->filterByAttributeId($attributeId)
                            ->useAttributeAvQuery()
                                ->useI18nQuery()
                                    ->where(\sprintf('CAST(attribute_av_i18n.title AS UNSIGNED) %s ?', $operator), (int) $raw)
                                ->endUse()
                            ->endUse();
                }
                $query
                    ->endUse()
                    ->endUse();
            }
        }
        if (!empty($rawAttributes)) {
            $query
                ->useProductSaleElementsQuery()
                    ->useAttributeCombinationQuery()
                        ->filterByAttributeAvId($rawAttributes, Criteria::IN)
                    ->endUse()
                ->endUse()
            ;
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
     * The attribute values a product set offers are a DISTINCT over the combinations
     * of its sale elements: one query instead of one per sale element.
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
            ->select(['AttributeId', 'AttributeAvId'])
            ->distinct()
            ->find()
            ->getData();

        if ($pairs === []) {
            return [];
        }

        $attributes = AttributeQuery::create()
            ->filterById(array_column($pairs, 'AttributeId'), Criteria::IN)
            ->orderByPosition()
            ->find()
            ->toKeyIndex();

        $attributeAvs = AttributeAvQuery::create()
            ->filterById(array_column($pairs, 'AttributeAvId'), Criteria::IN)
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
                    ->setTitle($this->localizedTitle($attributeAv, $locale));
        }

        return $values;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Attribute();
    }
}
