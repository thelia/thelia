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

namespace Thelia\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;

/**
 *
 * Class Cart where all actions are manage like adding, modifying or delete items.
 *
 * Class Cart
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Cart extends BaseAction implements EventSubscriberInterface
{
    /**
     *
     * add an article in the current cart
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function addItem(CartEvent $event)
    {

        $cart = $event->getCart();
        $newness = $event->getNewness();
        $append = $event->getAppend();
        $quantity = $event->getQuantity();

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProduct();

        $cartItem = $this->findItem($cart->getId(), $productId, $productSaleElementsId);

        if ($cartItem === null || $newness) {
            $productPrice = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($productSaleElementsId)
                ->findOne();

            $event->setCartItem(
                $this->doAddItem($cart, $productId, $productPrice->getProductSaleElements(), $quantity, $productPrice)
            );
        }

        if ($append && $cartItem !== null) {
            $cartItem->addQuantity($quantity)
                ->save();

            $event->setCartItem(
                $cartItem
            );
        }
    }

    /**
     *
     * Delete specify article present into cart
     *
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function deleteItem(CartEvent $event)
    {
        if (null !== $cartItemId = $event->getCartItem()) {
            $cart = $event->getCart();
            CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->delete();

        }
    }

    /**
     * Clear the cart
     * @param CartEvent $event
     */
    public function clear(CartEvent $event)
    {
        if (null !== $cart = $event->getCart()) {
            $cart->delete();
        }
    }

    /**
     *
     * Modify article's quantity
     *
     * don't use Form here just test the Request.
     *
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function changeItem(CartEvent $event)
    {
        if ((null !== $cartItemId = $event->getCartItem()) && (null !== $quantity = $event->getQuantity())) {
            $cart = $event->getCart();

            $cartItem = CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->findOne();

            if ($cartItem) {
                $event->setCartItem(
                    $this->updateQuantity($cartItem, $quantity)
                );
            }
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CART_ADDITEM => array("addItem", 128),
            TheliaEvents::CART_DELETEITEM => array("deleteItem", 128),
            TheliaEvents::CART_UPDATEITEM => array("changeItem", 128),
            TheliaEvents::CART_CLEAR => array("clear", 128),
        );
    }

    /**
     * increase the quantity for an existing cartItem
     *
     * @param CartItem $cartItem
     * @param float    $quantity
     *
     * @return CartItem
     */
    protected function updateQuantity(CartItem $cartItem, $quantity)
    {
        $cartItem->setDisptacher($this->getDispatcher());
        $cartItem->updateQuantity($quantity)
            ->save();

        return $cartItem;
    }

    /**
     * try to attach a new item to an existing cart
     *
     * @param \Thelia\Model\Cart                $cart
     * @param int                               $productId
     * @param \Thelia\Model\ProductSaleElements $productSaleElements
     * @param float                             $quantity
     * @param ProductPrice                      $productPrice
     *
     * @return CartItem
     */
    protected function doAddItem(\Thelia\Model\Cart $cart, $productId, \Thelia\Model\ProductSaleElements $productSaleElements, $quantity, ProductPrice $productPrice)
    {
        $cartItem = new CartItem();
        $cartItem->setDisptacher($this->getDispatcher());
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($productPrice->getPrice())
            ->setPromoPrice($productPrice->getPromoPrice())
            ->setPromo($productSaleElements->getPromo())
            ->setPriceEndOfLife(time() + ConfigQuery::read("cart.priceEOF", 60*60*24*30))
            ->save();

        return $cartItem;
    }

    /**
     * find a specific record in CartItem table using the Cart id, the product id
     * and the product_sale_elements id
     *
     * @param  int           $cartId
     * @param  int           $productId
     * @param  int           $productSaleElementsId
     * @return ChildCartItem
     */
    protected function findItem($cartId, $productId, $productSaleElementsId)
    {
        return CartItemQuery::create()
            ->filterByCartId($cartId)
            ->filterByProductId($productId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne();
    }

}
