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

    /**
     * @param bool $append
     */
    public function setAppend($append): static
    {
        $this->append = $append;

        return $this;
    }

    /**
     * @return bool
     */
    public function getAppend()
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

    /**
     * @return CartItem
     */
    public function getCartItem()
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

    /**
     * @param bool $newness
     */
    public function setNewness($newness): static
    {
        $this->newness = $newness;

        return $this;
    }

    /**
     * @return bool
     */
    public function getNewness()
    {
        return $this->newness;
    }

    /**
     * @param int $product the product ID
     */
    public function setProduct($product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return int the product ID
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param int $productSaleElementsId
     */
    public function setProductSaleElementsId($productSaleElementsId): static
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    /**
     * @return int
     */
    public function getProductSaleElementsId()
    {
        return $this->productSaleElementsId;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
