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

use Front\Front;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\CartAdd;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class CartController extends BaseFrontController
{
    public function addItem()
    {
        $request = $this->getRequest();

        $cartAdd = $this->getAddCartForm($request);
        $message = null;

        try {
            $form = $this->validateForm($cartAdd);

            $cartEvent = $this->getCartEvent();

            $cartEvent->bindForm($form);

            $this->getDispatcher()->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);

            $this->afterModifyCart();

            if (! $this->changeViewForAjax()) {
                if (null !== $response = $this->generateSuccessRedirect($cartAdd)) {
                    return $response;
                }
            }
        } catch (PropelException $e) {
            Tlog::getInstance()->error(sprintf("Failed to add item to cart with message : %s", $e->getMessage()));
            $message = $this->getTranslator()->trans(
                "Failed to add this article to your cart, please try again",
                [],
                Front::MESSAGE_DOMAIN
            );
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        }

        if ($message) {
            $cartAdd->setErrorMessage($message);
            $this->getParserContext()->addForm($cartAdd);
        }
    }

    public function changeItem()
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItemId($this->getRequest()->get("cart_item"));
        $cartEvent->setQuantity($this->getRequest()->get("quantity"));

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token')
            );

            $this->dispatch(TheliaEvents::CART_UPDATEITEM, $cartEvent);

            $this->afterModifyCart();

            if (! $this->changeViewForAjax()) {
                if (null !== $successUrl = $this->getRequest()->get("success_url")) {
                    return $this->generateRedirect(URL::getInstance()->absoluteUrl($successUrl));
                }
            }
        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf("Failed to change cart item quantity: %s", $e->getMessage()));

            $this->getParserContext()->setGeneralError($e->getMessage());
        }
    }

    public function deleteItem()
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItemId($this->getRequest()->get("cart_item"));

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token')
            );

            $this->getDispatcher()->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);

            $this->afterModifyCart();
        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf("error during deleting cartItem with message : %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        if (! $this->changeViewForAjax()) {
            if (null !== $successUrl = $this->getRequest()->get("success_url")) {
                return $this->generateRedirect(URL::getInstance()->absoluteUrl($successUrl));
            }
        }
    }

    protected function changeViewForAjax()
    {
        // If this is an ajax request, and if the template allow us to return an ajax result
        if ($this->getRequest()->isXmlHttpRequest() && (0 === intval($this->getRequest()->get('no_ajax_check', 0)))) {
            $request = $this->getRequest();

            $view = $request->get('ajax-view', "includes/mini-cart");

            $request->attributes->set('_view', $view);

            return true;
        }

        return false;
    }

    public function changeCountry()
    {
        $redirectUrl = URL::getInstance()->absoluteUrl("/cart");
        $deliveryId = $this->getRequest()->get("country");
        $cookieName = ConfigQuery::read('front_cart_country_cookie_name', 'fcccn');
        $cookieExpires = ConfigQuery::read('front_cart_country_cookie_expires', 2592000);
        $cookieExpires = intval($cookieExpires) ?: 2592000;

        $cookie = new Cookie($cookieName, $deliveryId, time() + $cookieExpires, '/');

        $response = $this->generateRedirect($redirectUrl);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * @return \Thelia\Core\Event\Cart\CartEvent
     */
    protected function getCartEvent()
    {
        $cart = $this->getSession()->getSessionCart($this->getDispatcher());

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
        /** @var CartAdd $cartAdd */
        if ($request->isMethod("post")) {
            $cartAdd = $this->createForm(FrontForm::CART_ADD);
        } else {
            $cartAdd = $this->createForm(
                FrontForm::CART_ADD,
                FormType::class,
                array(),
                array(
                    'csrf_protection'   => false,
                )
            );
        }

        return $cartAdd;
    }

    /**
     * @throws PropelException
     */
    protected function afterModifyCart()
    {
        /* recalculate postage amount */
        $order = $this->getSession()->getOrder();
        if (null !== $order) {
            $deliveryModule = $order->getModuleRelatedByDeliveryModuleId();
            $deliveryAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress());

            if (null !== $deliveryModule && null !== $deliveryAddress) {
                $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

                $orderEvent = new OrderEvent($order);

                try {
                    $deliveryPostageEvent = new DeliveryPostageEvent(
                        $moduleInstance,
                        $this->getSession()->getSessionCart($this->getDispatcher()),
                        $deliveryAddress
                    );

                    $this->getDispatcher()->dispatch(
                        TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                        $deliveryPostageEvent
                    );

                    $postage = $deliveryPostageEvent->getPostage();

                    if (null !== $postage)  {
                        $orderEvent->setPostage($postage->getAmount());
                        $orderEvent->setPostageTax($postage->getAmountTax());
                        $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());
                    }

                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderEvent);
                } catch (\Exception $ex) {
                    // The postage has been chosen, but changes in the cart causes an exception.
                    // Reset the postage data in the order
                    $orderEvent->setDeliveryModule(0);

                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);
                }
            }
        }
    }
}
