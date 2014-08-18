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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Tools\ProductPriceTools;

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
        $currency = $cart->getCurrency();
        $customer = $cart->getCustomer();
        $discount = 0;

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProduct();

        $cartItem = $this->findItem($cart->getId(), $productId, $productSaleElementsId);

        if ($cartItem === null || $newness) {

            $productSaleElements = ProductSaleElementsQuery::create()
                ->findPk($productSaleElementsId);

            if (null !== $productSaleElements) {
                $productPrices = $productSaleElements->getPricesByCurrency($currency, $discount);
                $event->setCartItem(
                    $this->doAddItem($event->getDispatcher(), $cart, $productId, $productSaleElements, $quantity, $productPrices)
                );
            }
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
                    $this->updateQuantity($event->getDispatcher(), $cartItem, $quantity)
                );
            }
        }
    }

    public function updateCart(CurrencyChangeEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $cart = $session->getCart();
        if (null !== $cart) {
            $this->updateCartPrices($cart, $event->getCurrency());
        }
    }

    /**
     *
     * Refresh article's price
     *
     * @param \Thelia\Model\Cart     $cart
     * @param \Thelia\Model\Currency $currency
     */
    public function updateCartPrices(\Thelia\Model\Cart $cart, \Thelia\Model\Currency $currency)
    {

        $customer = $cart->getCustomer();
        $discount = 0;

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        // cart item
        foreach ($cart->getCartItems() as $cartItem) {
            $productSaleElements = $cartItem->getProductSaleElements();

            $productPrice = $productSaleElements->getPricesByCurrency($currency, $discount);

            $cartItem
                ->setPrice($productPrice->getPrice())
                ->setPromoPrice($productPrice->getPromoPrice());

            $cartItem->save();
        }

        // update the currency cart
        $cart->setCurrencyId($currency->getId());
        $cart->save();

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
            TheliaEvents::CHANGE_DEFAULT_CURRENCY => array("updateCart", 128),
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
    protected function updateQuantity(EventDispatcherInterface $dispatcher, CartItem $cartItem, $quantity)
    {
        $cartItem->setDisptacher($dispatcher);
        $cartItem->updateQuantity($quantity)
            ->save();

        return $cartItem;
    }

    /**
     * try to attach a new item to an existing cart
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \Thelia\Model\Cart                                          $cart
     * @param int                                                         $productId
     * @param \Thelia\Model\ProductSaleElements                           $productSaleElements
     * @param float                                                       $quantity
     * @param ProductPriceTools                                           $productPrices
     *
     * @return CartItem
     */
    protected function doAddItem(EventDispatcherInterface $dispatcher, \Thelia\Model\Cart $cart, $productId, \Thelia\Model\ProductSaleElements $productSaleElements, $quantity, ProductPriceTools $productPrices)
    {
        $cartItem = new CartItem();
        $cartItem->setDisptacher($dispatcher);
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($productPrices->getPrice())
            ->setPromoPrice($productPrices->getPromoPrice())
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
