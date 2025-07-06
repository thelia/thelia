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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Model\Brand;

class BrandFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['brand'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        $query->filterByBrandId($value);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        $brand = $activeRecord->getBrand();
        if (!$brand instanceof Brand) {
            return null;
        }

        return [
            [
                'id' => $brand->getId(),
                'title' => $brand->setLocale($locale)->getTitle(),
            ],
        ];
    }
}
