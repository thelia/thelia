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

class CartEvent extends ActionEvent
{
    protected $cart;
    protected $quantity;
    protected $append;
    protected $newness;
    protected $productSaleElementsId;
    protected $product;
    protected $cartItem;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * @param mixed $append
     */
    public function setAppend($append)
    {
        $this->append = $append;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAppend()
    {
        return $this->append;
    }

    /**
     * @param mixed $cartItem
     */
    public function setCartItem($cartItem)
    {
        $this->cartItem = $cartItem;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCartItem()
    {
        return $this->cartItem;
    }

    /**
     * @param mixed $newness
     */
    public function setNewness($newness)
    {
        $this->newness = $newness;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getNewness()
    {
        return $this->newness;
    }

    /**
     * @param mixed $product
     */
    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param mixed $productSaleElementsId
     */
    public function setProductSaleElementsId($productSaleElementsId)
    {
        $this->productSaleElementsId = $productSaleElementsId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProductSaleElementsId()
    {
        return $this->productSaleElementsId;
    }

    /**
     * @param mixed $quantity
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return mixed
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
