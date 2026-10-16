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

namespace Thelia\Domain\SaleUnit\Stock;

use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * Where the stock of a sale element is kept for a sale unit: its own row in
 * `sale_unit_product_sale_elements` when it has one, the shared national counter
 * otherwise. A read-side helper — the atomic decrement lives in StockDecrementer,
 * which targets the same table on the same condition.
 *
 * A null sale unit id is the single-shop path: there is nothing per unit to read,
 * the caller keeps the shared quantity it already holds.
 */
readonly class SaleUnitStockResolver
{
    /**
     * @return bool whether this sale unit keeps its own stock row for this sale element
     */
    public function hasOwnStock(?int $saleUnitId, int $productSaleElementsId): bool
    {
        if (null === $saleUnitId) {
            return false;
        }

        return SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($saleUnitId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->exists();
    }

    /**
     * The quantity to check availability against: the unit's own when it keeps one,
     * the national quantity handed in otherwise.
     */
    public function availableQuantity(?int $saleUnitId, int $productSaleElementsId, float $nationalQuantity): float
    {
        if (null === $saleUnitId) {
            return $nationalQuantity;
        }

        return $this->ownQuantity($saleUnitId, $productSaleElementsId) ?? $nationalQuantity;
    }

    /**
     * The quantity the sale unit keeps of its own, or null when it keeps no row for
     * the sale element and sells from the national counter.
     */
    public function ownQuantity(int $saleUnitId, int $productSaleElementsId): ?float
    {
        $row = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($saleUnitId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne();

        return null === $row ? null : (float) $row->getQuantity();
    }
}
