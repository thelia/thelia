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

namespace Thelia\Domain\SaleUnit\Cart;

/**
 * What became of one cart line when the cart moved to another sale unit: removed
 * (the unit has none left), reduced to what the unit holds, repriced, or several
 * of these. Prices are untaxed unit prices, as the cart line stores them.
 */
final readonly class CartLineChange
{
    public function __construct(
        public int $productSaleElementsId,
        public int $productId,
        public string $productRef,
        public float $previousQuantity,
        public float $quantity,
        public float $previousPrice,
        public float $price,
    ) {
    }

    public function isRemoved(): bool
    {
        return $this->quantity <= 0.0;
    }

    public function isQuantityReduced(): bool
    {
        return !$this->isRemoved() && $this->quantity < $this->previousQuantity;
    }

    public function isRepriced(): bool
    {
        return !$this->isRemoved() && abs($this->price - $this->previousPrice) >= 0.000001;
    }

    public function hasChanged(): bool
    {
        return $this->isRemoved() || $this->isQuantityReduced() || $this->isRepriced();
    }

    /**
     * @return array{product_sale_elements_id: int, product_id: int, ref: string, previous_quantity: float, quantity: float, previous_price: float, price: float}
     */
    public function toArray(): array
    {
        return [
            'product_sale_elements_id' => $this->productSaleElementsId,
            'product_id' => $this->productId,
            'ref' => $this->productRef,
            'previous_quantity' => $this->previousQuantity,
            'quantity' => $this->quantity,
            'previous_price' => $this->previousPrice,
            'price' => $this->price,
        ];
    }
}
