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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class CategoryFilter implements TheliaFilterInterface
{
    public function filter(ModelCriteria $query, $value): void
    {
        $query->useProductCategoryQuery()->filterByCategoryId($value)->endUse();
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['category'];
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale): ?array
    {
        if (empty($activeRecord->getCategories())) {
            return null;
        }
        $value = [];
        foreach ($activeRecord->getCategories() as $category) {
            $value[] =
                [
                    'id' => $category->getId(),
                    'title' => $category->setLocale($locale)->getTitle(),
                ]
            ;
        }

        return $value;
    }
}
