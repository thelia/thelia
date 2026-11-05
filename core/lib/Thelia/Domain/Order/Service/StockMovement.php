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

namespace Thelia\Domain\Order\Service;

use Thelia\Core\Event\ProductSaleElement\StockLevelChange;

/**
 * What one decrement did to the stock of its target: the sale element, or the own stock of a
 * sale unit for that sale element.
 */
final readonly class StockMovement
{
    /**
     * @param bool        $preorder    the units taken exceed the physical stock that was there (a stock below zero counts
     *                                 as none) on a sale element that sells below zero; only for a shop that checks the stock
     * @param string|null $restockDate the restock date of the sale element at that moment, `Y-m-d`; set only with $preorder
     */
    public function __construct(
        public int $productSaleElementsId,
        public ?int $saleUnitId,
        public float $previousQuantity,
        public float $newQuantity,
        public bool $preorder = false,
        public ?string $restockDate = null,
    ) {
    }

    /** Whether the movement landed on the own stock of a sale unit rather than on the sale element. */
    public function onSaleUnitStock(): bool
    {
        return null !== $this->saleUnitId;
    }

    public function toLevelChange(): StockLevelChange
    {
        return new StockLevelChange($this->productSaleElementsId, $this->previousQuantity, $this->newQuantity);
    }
}
