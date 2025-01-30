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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

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
        $query->useFeatureProductQuery()->filterByFeatureAvId($value)->endUse();
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

    public function getChoiceFilterType(ActiveRecordInterface $activeRecord): ActiveRecordInterface
    {
        return $activeRecord->getFeatureProductsJoinFeatureAv()->getFirst()->getFeature();
    }
}
