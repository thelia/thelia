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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CartEvent;
use Thelia\Form\CartAdd;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Action\Exception\FormValidationException;

/**
 *
 * Class Cart where all actions are manage like adding, modifying or delete items.
 *
 * Class Cart
 * @package Thelia\Action
 */
class Cart extends BaseAction implements EventSubscriberInterface
{
    /**
     *
     * add an article in the current cart
     * @param \Thelia\Core\Event\CartEvent $event
     */
    public function addArticle(CartEvent $event)
    {
        $request = $event->getRequest();
        $message = null;

        try {
            $cartAdd = $this->getAddCartForm($request);

            $form = $this->validateForm($cartAdd);

            $cart = $event->getCart();
            $newness = $form->get("newness")->getData();
            $append = $form->get("append")->getData();
            $quantity = $form->get("quantity")->getData();

            $productSaleElementsId = $form->get("product_sale_elements_id")->getData();
            $productId = $form->get("product")->getData();

            $cartItem = $this->findItem($cart->getId(), $productId, $productSaleElementsId);

            if ($cartItem === null || $newness) {
                $productPrice = ProductPriceQuery::create()
                    ->filterByProductSaleElementsId($productSaleElementsId)
                    ->findOne()
                ;

                $this->addItem($cart, $productId, $productSaleElementsId, $quantity, $productPrice);
            }

            if ($append && $cartItem !== null) {
                $this->updateQuantity($cartItem, $quantity);
            }

        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Failed to add item to cart with message : %s", $e->getMessage()));
            $message = "Failed to add this article to your cart, please try again";
        } catch (FormValidationException $e) {

            $message = $e->getMessage();
        }
        if ($message) {
            // The form has errors, propagate it.
            $this->propagateFormError($cartAdd, $message, $event);
        }

    }

    /**
     *
     * Delete specify article present into cart
     *
     * @param \Thelia\Core\Event\CartEvent $event
     */
    public function deleteArticle(CartEvent $event)
    {
        $request = $event->getRequest();

        if (null !== $cartItemId = $request->get('cartItem')) {
            $cart = $event->getCart();
            try {
                $cartItem = CartItemQuery::create()
                    ->filterByCartId($cart->getId())
                    ->filterById($cartItemId)
                    ->delete();
            } catch (PropelException $e) {
                \Thelia\Log\Tlog::getInstance()->error(sprintf("error during deleting cartItem with message : %s", $e->getMessage()));
            }

        }
    }

    /**
     *
     * Modify article's quantity
     *
     * don't use Form here just test the Request.
     *
     * @param \Thelia\Core\Event\CartEvent $event
     */
    public function modifyArticle(CartEvent $event)
    {
        $request = $event->getRequest();

        if (null !== $cartItemId = $request->get("cartItem") && null !== $quantity = $request->get("quantity")) {

            try {
                $cart = $event->getCart($request);

                $cartItem = CartItemQuery::create()
                    ->filterByCartId($cart->getId())
                    ->filterById($cartItemId)
                    ->findOne();

                if ($cartItem) {
                    $this->updateQuantity($cartItem, $quantity);
                }
            } catch (PropelException $e) {
                \Thelia\Log\Tlog::getInstance()->error(sprintf("error during updating cartItem with message : %s", $e->getMessage()));
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
            "action.addArticle" => array("addArticle", 128),
            "action.deleteArticle" => array("deleteArticle", 128),
            "action.changeArticle" => array("modifyArticle", 128),
        );
    }

    /**
     * increase the quantity for an existing cartItem
     *
     * @param CartItem $cartItem
     * @param float    $quantity
     */
    protected function updateQuantity(CartItem $cartItem, $quantity)
    {
        $cartItem->setDisptacher($this->getDispatcher());
        $cartItem->addQuantity($quantity)
            ->save();
    }

    /**
     * try to attach a new item to an existing cart
     *
     * @param \Thelia\Model\Cart $cart
     * @param int                $productId
     * @param int                $productSaleElementsId
     * @param float              $quantity
     * @param ProductPrice       $productPrice
     */
    protected function addItem(\Thelia\Model\Cart $cart, $productId, $productSaleElementsId, $quantity, ProductPrice $productPrice)
    {
        $cartItem = new CartItem();
        $cartItem->setDisptacher($this->getDispatcher());
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElementsId)
            ->setQuantity($quantity)
            ->setPrice($productPrice->getPrice())
            ->setPromoPrice($productPrice->getPromoPrice())
            ->setPriceEndOfLife(time() + ConfigQuery::read("cart.priceEOF", 60*60*24*30))
            ->save();
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
     * Find the good way to construct the cart form
     *
     * @param  Request $request
     * @return CartAdd
     */
    private function getAddCartForm(Request $request)
    {
        if ($request->isMethod("post")) {
            $cartAdd = new CartAdd($request);
        } else {
            $cartAdd = new CartAdd(
                $request,
                "form",
                array(),
                array(
                    'csrf_protection'   => false,
                )
            );
        }

        return $cartAdd;
    }

}
