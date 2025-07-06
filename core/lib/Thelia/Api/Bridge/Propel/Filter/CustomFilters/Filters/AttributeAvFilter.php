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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
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

    public function filter(ModelCriteria $query, $value): void
    {
        $query
            ->useProductSaleElementsQuery()
                    ->useAttributeCombinationQuery()
                        ->filterByAttributeAvId($value)
                ->endUse()
            ->endUse();
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
                    [
                        'mainTitle' => $attributeAv->getAttribute()->setLocale($locale)->getTitle(),
                        'mainId' => $attributeAv->getAttribute()->getId(),
                        'id' => $attributeAv->getAttributeAvId(),
                        'title' => $attributeAv->getAttributeAv()->setLocale($locale)->getTitle(),
                    ]
                ;
            }
        }

        return $value;
    }

    public function getChoiceFilterType(): ActiveRecordInterface
    {
        return new Attribute();
    }
}
