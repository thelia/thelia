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
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Splits a listing between the products a buyer can have now and the others.
 *
 * A product is in stock when it is virtual, or when one of its visible combinations has a
 * positive quantity: a buyer picks the size he wants, so one available size is enough, the
 * rule the promotion facet already follows. Everything else is on order or out of stock,
 * a product without any combination included, so that it stays counted somewhere.
 *
 * When the shop does not check stock (`check-available-stock` off), every product can be
 * ordered whatever its quantity, and the whole listing is in stock.
 */
final readonly class AvailabilityFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface
{
    use SelectedValuesTrait;

    public const IN_STOCK = 1;

    public const OUT_OF_STOCK = 2;

    public function __construct(private Translator $translator)
    {
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['availability'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        $selected = array_values(array_intersect(
            [self::IN_STOCK, self::OUT_OF_STOCK],
            array_map('intval', array_filter($this->flattenSelectedValues($value), 'is_numeric')),
        ));

        // Both values together keep every product, and no value is no selection at all.
        if (\count($selected) !== 1) {
            return;
        }

        if (!ConfigQuery::checkAvailableStock()) {
            if ($selected[0] === self::OUT_OF_STOCK) {
                $query->where('1 <> 1');
            }

            return;
        }

        if ($selected[0] === self::IN_STOCK) {
            $this->restrictToInStock($query);

            return;
        }

        $this->restrictToOutOfStock($query);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (!$activeRecord instanceof Product) {
            return null;
        }

        $inStock = !ConfigQuery::checkAvailableStock()
            || $this->inStockProducts()->filterById($activeRecord->getId())->exists();

        return [$this->value($inStock ? self::IN_STOCK : self::OUT_OF_STOCK, $locale)];
    }

    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $total = \count(array_unique($resourceIds));
        $inStock = $total;

        if (ConfigQuery::checkAvailableStock()) {
            $inStock = \count($this->inStockProducts()
                ->filterById($resourceIds, Criteria::IN)
                ->select(['Id'])
                ->find()
                ->getData());
        }

        // A value no product holds is not offered: it would narrow the listing to nothing.
        $values = [];

        if ($inStock > 0) {
            $values[] = $this->value(self::IN_STOCK, $locale)->setCount($inStock);
        }

        if ($total - $inStock > 0) {
            $values[] = $this->value(self::OUT_OF_STOCK, $locale)->setCount($total - $inStock);
        }

        return $values;
    }

    private function inStockProducts(): ProductQuery
    {
        $query = ProductQuery::create();
        $this->restrictToInStock($query);

        return $query;
    }

    /**
     * The OR binds to the criterion added just before it, which is why the two halves of the
     * rule are written back to back. Each half joins the combinations under its own alias, so
     * the price ordering and the flag facets, which join the same table, are left alone.
     */
    private function restrictToInStock(ModelCriteria $query): void
    {
        $query
            ->filterBy('Virtual', 1)
            ->_or()
            ->useExistsQuery('ProductSaleElements', 'availability_in_stock_pse')
                ->filterByVisible(true)
                ->filterByQuantity(0, Criteria::GREATER_THAN)
            ->endUse();
    }

    private function restrictToOutOfStock(ModelCriteria $query): void
    {
        $query
            ->filterBy('Virtual', 0)
            ->useNotExistsQuery('ProductSaleElements', 'availability_out_of_stock_pse')
                ->filterByVisible(true)
                ->filterByQuantity(0, Criteria::GREATER_THAN)
            ->endUse();
    }

    private function value(int $id, string $locale): FilterValue
    {
        return (new FilterValue())
            ->setId($id)
            ->setTitle($this->translator->trans(
                id: $id === self::IN_STOCK ? 'In stock' : 'On order or out of stock',
                locale: $locale,
            ));
    }
}
