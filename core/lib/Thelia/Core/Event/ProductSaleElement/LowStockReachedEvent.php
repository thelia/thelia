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

namespace Thelia\Core\Event\ProductSaleElement;

use Thelia\Core\Event\ActionEvent;

/**
 * The physical stock of a sale element went from above its threshold to at or under it: its national
 * counter, or the own stock of the sale unit given by getSaleUnitId().
 */
class LowStockReachedEvent extends ActionEvent
{
    public function __construct(
        private readonly int $productSaleElementsId,
        private readonly float $quantity,
        private readonly float $threshold,
        private readonly ?int $saleUnitId = null,
    ) {
    }

    /** The sale unit whose own stock ran low; null for the national counter. */
    public function getSaleUnitId(): ?int
    {
        return $this->saleUnitId;
    }

    public function getProductSaleElementsId(): int
    {
        return $this->productSaleElementsId;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }
}
