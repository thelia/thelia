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

namespace Thelia\Domain\Catalog\Bundle;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Stock\DatabaseReservedQuantity;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The one place that decides whether a component can be sold and how much of it there is.
 *
 * A component is sellable when its sale element still exists (the item has not been
 * orphaned by a deletion), is online, belongs to a product that is online, and that product
 * is not a bundle itself. A bundle one of whose retained components is not sellable is not.
 *
 * The stock is the one the cart reads: the own stock of the current sale unit when it keeps
 * one for the sale element, the national counter otherwise, plus what the sale mode of the
 * component allows below zero.
 *
 * Whether the current visitor may see the product of a component (a private drop, a rule of a
 * module) is a different question with a different answer for each visitor, asked of
 * ProductVisibility through hiddenFromVisitor() by the readers that sell to a visitor; the
 * rest of the answers here do not depend on who asks.
 */
final readonly class BundleComponentReader
{
    public function __construct(
        private SaleUnitAvailableStock $saleUnitAvailableStock,
        private ProductVisibility $productVisibility,
        private ?DatabaseReservedQuantity $reservedQuantity = null,
    ) {
    }

    /**
     * The sale elements, among the ones asked, whose product the current visitor may not see:
     * the visibility of the catalogue (ProductVisibility), applied to the product of each.
     * Empty without a query when nothing is hidden from the visitor.
     *
     * @param list<int|null> $productSaleElementsIds a null (an orphaned item) is ignored
     *
     * @return list<int>
     */
    public function hiddenFromVisitor(array $productSaleElementsIds): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', array_filter($productSaleElementsIds, static fn (?int $id): bool => null !== $id))));
        $clause = $this->productVisibility->visibleProductClause(ProductSaleElementsTableMap::COL_PRODUCT_ID);

        if (null === $clause || [] === $productSaleElementsIds) {
            return [];
        }

        $visible = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->where($clause)
            ->select('Id')
            ->find()
            ->toArray();

        return array_values(array_diff($productSaleElementsIds, array_map('intval', $visible)));
    }

    /**
     * One query for the sale elements, one more for the stock of the sale unit when a unit is resolved,
     * one more for what the carts hold when the shop reserves its stock.
     *
     * @param list<int|null> $productSaleElementsIds a null (an orphaned item) is ignored
     * @param int|null       $excludedCartId         the cart asking, whose own reservations are not held against it;
     *                                               null counts what every cart holds
     * @param int|null       $saleUnitId             the sale unit of that cart, for the stock its reservations are counted on
     *
     * @return array<int, BundleComponent> keyed by sale element id; a sale element that does not exist is absent
     */
    public function read(array $productSaleElementsIds, ?int $excludedCartId = null, ?int $saleUnitId = null): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', array_filter($productSaleElementsIds, static fn (?int $id): bool => null !== $id))));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $rows = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->joinProduct()
            ->withColumn('Product.Visible', 'ProductVisible')
            ->withColumn('Product.IsBundle', 'ProductIsBundle')
            ->withColumn('Product.Virtual', 'ProductVirtual')
            ->select(['Id', 'ProductId', 'Quantity', 'OutOfStockPolicy', 'PreorderLimit', 'Visible', 'ProductVisible', 'ProductIsBundle', 'ProductVirtual'])
            ->find();

        $ownQuantities = $this->saleUnitAvailableStock->ownQuantities($productSaleElementsIds);
        $reserved = $this->reservedQuantity?->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $excludedCartId) ?? [];
        $components = [];

        foreach ($rows as $row) {
            $id = (int) $row['Id'];

            // The one rule of what may be sold (SellableStock): the stock the cart reads, and
            // what the sale mode of the component allows below zero, less what the carts hold.
            $physical = $ownQuantities[$id] ?? (float) $row['Quantity'];
            $ceiling = SellableStock::ceilingOf(
                $physical,
                OutOfStockPolicy::fromStored(null === $row['OutOfStockPolicy'] ? null : (int) $row['OutOfStockPolicy']),
                null === $row['PreorderLimit'] ? null : (float) $row['PreorderLimit'],
            );

            $components[$id] = new BundleComponent(
                $id,
                (int) $row['ProductId'],
                self::satisfiesSellability((bool) $row['Visible'], (bool) $row['ProductVisible'], (bool) $row['ProductIsBundle']),
                (bool) $row['ProductVirtual'],
                null === $ceiling ? 0.0 : max(0.0, $ceiling - ($reserved[$id] ?? 0.0)),
                null === $ceiling,
            );
        }

        return $components;
    }

    /**
     * The same predicate as a SQL subquery on the sale elements that can be sold as a
     * component, for the readers that filter in the query (the front API).
     */
    public static function sellableSaleElementsSubquery(): string
    {
        return \sprintf(
            'SELECT %s FROM %s INNER JOIN %s ON %s = %s WHERE %s = 1 AND %s = 1 AND %s = 0',
            ProductSaleElementsTableMap::COL_ID,
            ProductSaleElementsTableMap::TABLE_NAME,
            ProductTableMap::TABLE_NAME,
            ProductTableMap::COL_ID,
            ProductSaleElementsTableMap::COL_PRODUCT_ID,
            ProductSaleElementsTableMap::COL_VISIBLE,
            ProductTableMap::COL_VISIBLE,
            ProductTableMap::COL_IS_BUNDLE,
        );
    }

    private static function satisfiesSellability(bool $saleElementsOnline, bool $productOnline, bool $productIsBundle): bool
    {
        return $saleElementsOnline && $productOnline && !$productIsBundle;
    }
}
