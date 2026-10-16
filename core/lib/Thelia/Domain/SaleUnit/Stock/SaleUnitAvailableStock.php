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

use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\ProductSaleElements;

/**
 * The stock a visitor can buy from, for the sale unit of the current request: the
 * unit's own row when it keeps one for the sale element, the national counter
 * otherwise. What the cart and the front API read, so a visitor is offered what
 * the checkout will then decrement.
 *
 * With no sale unit resolved, a single shop among them, nothing is queried: the
 * national quantity the sale element already holds is the answer.
 */
readonly class SaleUnitAvailableStock
{
    public function __construct(
        private SaleUnitContext $saleUnitContext,
        private SaleUnitStockResolver $saleUnitStockResolver,
    ) {
    }

    /**
     * The quantity the current sale unit keeps of the sale element, or null when no
     * unit is resolved or the unit sells it from the national counter.
     */
    public function ownQuantity(int $productSaleElementsId): ?float
    {
        $saleUnit = $this->saleUnitContext->getSaleUnit();

        if (null === $saleUnit) {
            return null;
        }

        return $this->saleUnitStockResolver->ownQuantity((int) $saleUnit->getId(), $productSaleElementsId);
    }

    /**
     * The quantities the current sale unit keeps of its own for a batch of sale
     * elements, in one query; empty, without a query, when no unit is resolved. A
     * sale element absent from the answer sells from the national counter.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> keyed by sale element id
     */
    public function ownQuantities(array $productSaleElementsIds): array
    {
        $saleUnit = $this->saleUnitContext->getSaleUnit();

        if (null === $saleUnit) {
            return [];
        }

        return $this->saleUnitStockResolver->ownQuantities((int) $saleUnit->getId(), $productSaleElementsIds);
    }

    public function availableQuantity(ProductSaleElements $productSaleElements): float
    {
        return $this->ownQuantity((int) $productSaleElements->getId()) ?? (float) $productSaleElements->getQuantity();
    }
}
