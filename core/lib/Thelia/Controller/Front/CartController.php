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
namespace Thelia\Controller\Front;

use Propel\Runtime\Exception\PropelException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Event\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Form\CartAdd;

class CartController extends BaseFrontController
{
    use \Thelia\Cart\CartTrait;

    public function addArticle()
    {
        $request = $this->getRequest();

        $cartAdd = $this->getAddCartForm($request);
        $message = null;

        try {
            $form = $this->validateForm($cartAdd);

            $cartEvent = $this->getCartEvent();
            $cartEvent->newness = $form->get("newness")->getData();
            $cartEvent->append = $form->get("append")->getData();
            $cartEvent->quantity = $form->get("quantity")->getData();
            $cartEvent->productSaleElementsId = $form->get("product_sale_elements_id")->getData();
            $cartEvent->product = $form->get("product")->getData();

            $this->getDispatcher()->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);

            $this->redirectSuccess();

        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Failed to add item to cart with message : %s", $e->getMessage()));
            $message = "Failed to add this article to your cart, please try again";
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        }

        if ($message) {
            $cartAdd->setErrorMessage($e->getMessage());
            $this->getParserContext()->setErrorForm($cartAdd);
        }
    }

    public function changeArticle()
    {
        $cartEvent = $this->getCartEvent();

        $this->dispatch(TheliaEvents::CART_CHANGEITEM, $cartEvent);

        $this->redirectSuccess();
    }

    public function deleteArticle()
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->cartItem = $this->getRequest()->get("cartItem");

        try {
            $this->getDispatcher()->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);
        } catch (PropelException $e)
        {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during deleting cartItem with message : %s", $e->getMessage()));
        }

        $this->redirectSuccess();
    }

    /**
     * use Thelia\Cart\CartTrait for searching current cart or create a new one
     *
     * @return CartEvent
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
