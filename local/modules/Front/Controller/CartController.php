<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Front\Controller;

use Propel\Runtime\Exception\PropelException;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Form\CartAdd;

class CartController extends BaseFrontController
{
    use \Thelia\Cart\CartTrait;

    public function addItem()
    {
        $request = $this->getRequest();

        $cartAdd = $this->getAddCartForm($request);
        $message = null;

        try {
            $form = $this->validateForm($cartAdd);

            $cartEvent = $this->getCartEvent();
            $cartEvent->setNewness($form->get("newness")->getData());
            $cartEvent->setAppend($form->get("append")->getData());
            $cartEvent->setQuantity($form->get("quantity")->getData());
            $cartEvent->setProductSaleElementsId($form->get("product_sale_elements_id")->getData());
            $cartEvent->setProduct($form->get("product")->getData());

            $this->getDispatcher()->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);

            $this->redirectSuccess();

        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Failed to add item to cart with message : %s", $e->getMessage()));
            $message = "Failed to add this article to your cart, please try again";
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        }

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $request->attributes->set('_view', "includes/mini-cart");
        }

        if ($message) {
            $cartAdd->setErrorMessage($message);
            $this->getParserContext()->addForm($cartAdd);
        }
    }

    public function changeItem()
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItem($this->getRequest()->get("cart_item"));
        $cartEvent->setQuantity($this->getRequest()->get("quantity"));

        try {
            $this->dispatch(TheliaEvents::CART_UPDATEITEM, $cartEvent);

            $this->redirectSuccess();
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

    }

    public function deleteItem()
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItem($this->getRequest()->get("cart_item"));

        try {
            $this->getDispatcher()->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);

            $this->redirectSuccess();
        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during deleting cartItem with message : %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

    }

    /**
     * use Thelia\Cart\CartTrait for searching current cart or create a new one
     *
     * @return \Thelia\Core\Event\Cart\CartEvent
     */
    protected function getCartEvent()
    {
        $cart = $this->getCart($this->getRequest());

        return new CartEvent($cart);
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
