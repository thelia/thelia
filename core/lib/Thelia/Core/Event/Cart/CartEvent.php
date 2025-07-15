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

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;

class CartEvent extends ActionEvent
{
    protected $quantity;

    protected $append;

    protected $newness;

    protected $productSaleElementsId;

    protected $product;

    protected $cartItem;

    protected $cartItemId;

    public function __construct(protected Cart $cart)
    {
    }

    public function setAppend(bool $append): static
    {
        $this->append = $append;

        return $this;
    }

    public function getAppend(): bool
    {
        return $this->append;
    }

    public function setCartItem(CartItem $cartItem): static
    {
        $this->cartItem = $cartItem;

        return $this;
    }

    /**
     * Clear the current cart item.
     */
    public function clearCartItem(): static
    {
        $this->cartItem = null;

        return $this;
    }

    public function getCartItem(): CartItem
    {
        return $this->cartItem;
    }

    public function getCartItemId()
    {
        return $this->cartItemId;
    }

    /**
     * @return $this
     */
    public function setCartItemId($cartItemId): static
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    public function setNewness(bool $newness): static
    {
        $this->newness = $newness;

        return $this;
    }

    public function getNewness(): bool
    {
        return $this->newness;
    }

    /**
     * @param int $product the product ID
     */
    public function setProduct(int $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return int the product ID
     */
    public function getProduct(): int
    {
        return $this->product;
    }

    public function setProductSaleElementsId(int $productSaleElementsId): static
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    public function getProductSaleElementsId(): int
    {
        return $this->productSaleElementsId;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
