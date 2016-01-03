<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;

class CartEvent extends ActionEvent
{
    protected $cart;
    protected $quantity;
    protected $append;
    protected $newness;
    protected $productSaleElementsId;
    protected $product;
    protected $cartItem;

    protected $cartItemId;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * @param  bool      $append
     * @return CartEvent
     */
    public function setAppend($append)
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

    /**
     * @param  CartItem $cartItem
     * @return CartEvent
     */
    public function setCartItem(CartItem $cartItem)
    {
        $this->cartItem = $cartItem;

        return $this;
    }

    /**
     * Clear the current cart item
     *
     * @return CartEvent
     */
    public function clearCartItem()
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

    /**
     * @return mixed
     */
    public function getCartItemId()
    {
        return $this->cartItemId;
    }

    /**
     * @param mixed $cartItemId
     * @return $this
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    /**
     * @param  bool      $newness
     * @return CartEvent
     */
    public function setNewness($newness)
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
     * @param  int       $product the product ID
     * @return CartEvent
     */
    public function setProduct($product)
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
     * @param  int       $productSaleElementsId
     * @return CartEvent
     */
    public function setProductSaleElementsId($productSaleElementsId)
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
     * @param  int       $quantity
     * @return CartEvent
     */
    public function setQuantity($quantity)
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

    /**
     * @return \Thelia\Model\Cart
     */
    public function getCart()
    {
        return $this->cart;
    }
}
