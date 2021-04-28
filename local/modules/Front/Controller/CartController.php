<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Front\Controller;

use Front\Front;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\CartAdd;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class CartController extends BaseFrontController
{
    public function addItem(EventDispatcherInterface $eventDispatcher)
    {
        $request = $this->getRequest();

        $cartAdd = $this->getAddCartForm($request);
        $message = null;

        try {
            $form = $this->validateForm($cartAdd);

            $cartEvent = $this->getCartEvent($eventDispatcher);

            $cartEvent->bindForm($form);

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);

            $this->afterModifyCart($eventDispatcher);

            if (!$this->changeViewForAjax()) {
                if (null !== $response = $this->generateSuccessRedirect($cartAdd)) {
                    return $response;
                }
            }
        } catch (PropelException $e) {
            $this->logger->error(sprintf('Failed to add item to cart with message : %s', $e->getMessage()));
            $message = $this->getTranslator()->trans(
                'Failed to add this article to your cart, please try again',
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

    public function changeItem(EventDispatcherInterface $eventDispatcher)
    {
        $cartEvent = $this->getCartEvent($eventDispatcher);
        $cartEvent->setCartItemId($this->getRequest()->get('cart_item'));
        $cartEvent->setQuantity($this->getRequest()->get('quantity'));

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token')
            );

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_UPDATEITEM);

            $this->afterModifyCart($eventDispatcher);

            if (!$this->changeViewForAjax()) {
                if (null !== $successUrl = $this->getRequest()->get('success_url')) {
                    return $this->generateRedirect(URL::getInstance()->absoluteUrl($successUrl));
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to change cart item quantity: %s', $e->getMessage()));

            $this->getParserContext()->setGeneralError($e->getMessage());
        }
    }

    public function deleteItem(EventDispatcherInterface $eventDispatcher)
    {
        $cartEvent = $this->getCartEvent($eventDispatcher);
        $cartEvent->setCartItemId($this->getRequest()->get('cart_item'));

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token')
            );

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_DELETEITEM);

            $this->afterModifyCart($eventDispatcher);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('error during deleting cartItem with message : %s', $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        if (!$this->changeViewForAjax()) {
            if (null !== $successUrl = $this->getRequest()->get('success_url')) {
                return $this->generateRedirect(URL::getInstance()->absoluteUrl($successUrl));
            }
        }
    }

    protected function changeViewForAjax()
    {
        // If this is an ajax request, and if the template allow us to return an ajax result
        if ($this->getRequest()->isXmlHttpRequest() && (0 === (int) ($this->getRequest()->get('no_ajax_check', 0)))) {
            $request = $this->getRequest();

            $view = $request->get('ajax-view', 'includes/mini-cart');

            $request->attributes->set('_view', $view);

            return true;
        }

        return false;
    }

    public function changeCountry()
    {
        $redirectUrl = URL::getInstance()->absoluteUrl('/cart');
        $deliveryId = $this->getRequest()->get('country');
        $cookieName = ConfigQuery::read('front_cart_country_cookie_name', 'fcccn');
        $cookieExpires = ConfigQuery::read('front_cart_country_cookie_expires', 2592000);
        $cookieExpires = (int) $cookieExpires ?: 2592000;

        $cookie = new Cookie($cookieName, $deliveryId, time() + $cookieExpires, '/');

        $response = $this->generateRedirect($redirectUrl);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * @return \Thelia\Core\Event\Cart\CartEvent
     */
    protected function getCartEvent(EventDispatcherInterface $eventDispatcher)
    {
        $cart = $this->getSession()->getSessionCart($eventDispatcher);

        return new CartEvent($cart);
    }

    /**
     * Find the good way to construct the cart form.
     *
     * @return CartAdd
     */
    private function getAddCartForm(Request $request)
    {
        /* @var CartAdd $cartAdd */
        if ($request->isMethod('post')) {
            $cartAdd = $this->createForm(FrontForm::CART_ADD);
        } else {
            $cartAdd = $this->createForm(
                FrontForm::CART_ADD,
                FormType::class,
                [],
                [
                    'csrf_protection' => false,
                ]
            );
        }

        return $cartAdd;
    }

    /**
     * @throws PropelException
     */
    protected function afterModifyCart(EventDispatcherInterface $eventDispatcher): void
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
                        $this->getSession()->getSessionCart($eventDispatcher),
                        $deliveryAddress
                    );

                    $eventDispatcher->dispatch(
                        $deliveryPostageEvent,
                        TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                    );

                    $postage = $deliveryPostageEvent->getPostage();

                    if (null !== $postage) {
                        $orderEvent->setPostage($postage->getAmount());
                        $orderEvent->setPostageTax($postage->getAmountTax());
                        $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());
                    }

                    $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_POSTAGE);
                } catch (\Exception $ex) {
                    // The postage has been chosen, but changes in the cart causes an exception.
                    // Reset the postage data in the order
                    $orderEvent->setDeliveryModule(0);

                    $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_MODULE);
                }
            }
        }
    }
}
