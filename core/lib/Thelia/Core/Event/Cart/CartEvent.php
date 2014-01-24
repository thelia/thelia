<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Cart;

use Symfony\Component\EventDispatcher\Event;
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