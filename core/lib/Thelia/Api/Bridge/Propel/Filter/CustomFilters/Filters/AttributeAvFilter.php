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
use Thelia\Model\Attribute;

class AttributeAvFilter implements TheliaFilterInterface, TheliaChoiceFilterInterface
{
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
        foreach ($value as $attributeId => $childValue) {
            foreach ($childValue as $type => $raw) {
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
                if (!$isMinOrMaxFilter) {
                    $query->filterByAttributeAvId($raw);
                }

                $query
                    ->endUse()
                    ->endUse();
            }
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
                        ->setMainTitle($attributeAv->getAttribute()->setLocale($locale)->getTitle())
                        ->setMainId($attributeAv->getAttribute()->getId())
                        ->setId($attributeAv->getAttributeAvId())
                        ->setTitle($attributeAv->getAttributeAv()->setLocale($locale)->getTitle());
            }
        }

        return $value;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Attribute();
    }
}
