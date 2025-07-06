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
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPriceQuery;

class PriceFilter // implements TheliaFilterInterface
{
    // maybe we will use this filter but not for the moment
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['prices', 'price'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        foreach ($value as $key => $item) {
            switch ($key) {
                case 'between':
                    $this->betweenFilter(query: $query, item: $item);
                    // no break
                case 'order':
                    $this->orderByPrice(query: $query, order: $item);
            }
        }
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        $defaultCurrencyId = CurrencyQuery::create()->filterByByDefault(true)->findOne()?->getId();
        $defaultPseId = $activeRecord->getDefaultSaleElements()->getId();
        if (!$defaultPseId || !$defaultCurrencyId) {
            return null;
        }

        return ['price' => ProductPriceQuery::create()->filterByCurrencyId($defaultCurrencyId)->filterByProductSaleElementsId($defaultPseId)->findOne()?->getPrice()];
    }

    private function betweenFilter(ModelCriteria $query, string $item): void
    {
        $values = explode('..', $item);
        $min = filter_var($values[0], \FILTER_VALIDATE_FLOAT);
        $max = filter_var($values[1], \FILTER_VALIDATE_FLOAT);
        $query
            ->useProductSaleElementsQuery()
            ->filterByIsDefault(1)
            ->useProductPriceQuery()
            ->filterByPrice(['min' => $min, 'max' => $max])
            ->endUse()
            ->endUse();
    }

    private function orderByPrice(ModelCriteria $query, string $order): void
    {
        if ($order === 'asc') {
            $query->useProductSaleElementsQuery()
                ->useProductPriceQuery()
                ->orderByPrice('ASC')
                ->endUse()
                ->endUse();
        } elseif ($order === 'desc') {
            $query->useProductSaleElementsQuery()
                ->useProductPriceQuery()
                ->orderByPrice('DESC')
                ->endUse()
                ->endUse();
        }
    }
}
