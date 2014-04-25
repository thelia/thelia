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
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\Currency;
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
        $currency = $cart->getCurrency();
        $defaultCurrency = Currency::getDefaultCurrency();

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProduct();

        $cartItem = $this->findItem($cart->getId(), $productId, $productSaleElementsId);

        $price = 0.0;
        $promoPrice = 0.0;

        if ($cartItem === null || $newness) {

            $productPrice = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($productSaleElementsId)
                ->filterByCurrencyId($cart->getCurrencyId())
                ->findOne();

            if (null === $productPrice || $productPrice->getFromDefaultCurrency()) {
                // need to calculate the prices
                $productPrice = ProductPriceQuery::create()
                    ->filterByProductSaleElementsId($productSaleElementsId)
                    ->filterByCurrencyId($defaultCurrency->getId())
                    ->findOne();
                if (null !== $productPrice) {
                    $price = $productPrice->getPrice() * $currency->getRate();
                    $promoPrice = $productPrice->getPromoPrice() * $currency->getRate();
                }
            } else {
                $price = $productPrice->getPrice();
                $promoPrice = $productPrice->getPromoPrice();
            }

            $event->setCartItem(
                $this->doAddItem($event->getDispatcher(), $cart, $productId, $productPrice->getProductSaleElements(), $quantity, $price, $promoPrice)
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
     * @param \Thelia\Model\Base\Cart     $cart
     * @param \Thelia\Model\Base\Currency $currency
     */
    public function updateCartPrices(\Thelia\Model\Base\Cart $cart, Currency $currency)
    {

        // get default currency
        $defaultCurrency = Currency::getDefaultCurrency();

        $rate = 1.0;
        if ($currency !== $defaultCurrency) {
            $rate = $currency->getRate();
        }

        //Tlog::getInstance()->addDebug("UPDATE_CURRENCY : " . $rate . ' :: ' . $currency->getId() . ' :: ' . $defaultCurrency->getId() );

        $price = 0.0;
        $promoPrice = 0.0;

        // cart item
        foreach ($cart->getCartItems() as $cartItem) {
            $productSaleElementsId = $cartItem->getProductSaleElementsId();
            $productPrice = ProductPriceQuery::create()
                ->filterByCurrencyId($currency->getId())
                ->filterByProductSaleElementsId($productSaleElementsId)
                ->findOne();

            if (null === $productPrice || $productPrice->getFromDefaultCurrency()) {
                // we take the default currency and apply the taxe rate
                $productPrice = ProductPriceQuery::create()
                    ->filterByCurrencyId($defaultCurrency->getId())
                    ->filterByProductSaleElementsId($productSaleElementsId)
                    ->findOne();

                if (null === $productPrice) {
                    //Tlog::getInstance()->addDebug("BOOM");
                    continue;
                }
                //Tlog::getInstance()->addDebug("UPDATE_CURRENCY DYNAMIC ");
                $price = $productPrice->getPrice() * $rate;
                $promoPrice = $productPrice->getPromoPrice() * $rate;

            } else {
                // product price for default currency or manual price for other currency
                //Tlog::getInstance()->addDebug("UPDATE_CURRENCY REAL ");
                $price = $productPrice->getPrice();
                $promoPrice = $productPrice->getPromoPrice();
            }

            //Tlog::getInstance()->addDebug("UPDATE_CURRENCY : " . $price . " :: " . $promoPrice);

            // We have
            $cartItem
                ->setPrice($price)
                ->setPromoPrice($promoPrice);

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
     * @param \Thelia\Model\Cart                $cart
     * @param int                               $productId
     * @param \Thelia\Model\ProductSaleElements $productSaleElements
     * @param float                             $quantity
     * @param float                             $price
     * @param float                             $promoPrice
     *
     * @return CartItem
     */
    protected function doAddItem(EventDispatcherInterface $dispatcher, \Thelia\Model\Cart $cart, $productId, \Thelia\Model\ProductSaleElements $productSaleElements, $quantity, $price, $promoPrice)
    {
        $cartItem = new CartItem();
        $cartItem->setDisptacher($dispatcher);
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setPromoPrice($promoPrice)
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

    /**
     * Returns the session from the current request
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        return $this->request->getSession();
    }

}
