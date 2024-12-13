<?php

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;

class PriceFilter implements TheliaFilterInterface
{
    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['prices','price'];
    }

    public function filter(ModelCriteria $query, $value): void
    {
        foreach ($value as $key => $item) {
            switch ($key){
                case "between":
                    $this->betweenFilter(query: $query,item: $item);
                case "order":
                    $this->orderByPrice(query: $query, order: $item);
            }
        }
    }

    public function getValue(ActiveRecordInterface $activeRecord): array
    {
        return [];
    }

    private function betweenFilter(ModelCriteria $query, string $item): void
    {
        $values = explode("..", $item);
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
