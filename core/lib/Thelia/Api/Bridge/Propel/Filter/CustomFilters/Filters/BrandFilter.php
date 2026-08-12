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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\FilterValue;
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;
use Thelia\Model\ProductQuery;

class BrandFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface
{
    use LocalizedTitleTrait;

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['brand'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        foreach ($value as $id => $childValue) {
            foreach ($childValue as $type => $brandId) {
                $query->filterByBrandId($brandId);
            }
        }
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        $brand = $activeRecord->getBrand();

        if (!$brand instanceof Brand) {
            return null;
        }

        return [
            (new FilterValue())
                ->setId($brand->getId())
                ->setTitle($this->localizedTitle($brand, $locale)),
        ];
    }

    /**
     * The brands of a product set are one DISTINCT away; reading them product by
     * product only to deduplicate them afterwards is what made this expensive.
     */
    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $brandIds = ProductQuery::create()
            ->filterById($resourceIds, Criteria::IN)
            ->filterByBrandId(null, Criteria::ISNOTNULL)
            ->select('BrandId')
            ->distinct()
            ->find()
            ->getData();

        if ($brandIds === []) {
            return [];
        }

        $values = [];

        foreach (BrandQuery::create()->filterById($brandIds, Criteria::IN)->orderByPosition()->find() as $brand) {
            $values[] =
                (new FilterValue())
                    ->setId($brand->getId())
                    ->setTitle($this->localizedTitle($brand, $locale));
        }

        return $values;
    }
}
