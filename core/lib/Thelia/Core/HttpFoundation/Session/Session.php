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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session as BaseSession;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Tools\URL;

/**
 *
 * extends mfony\Component\HttpFoundation\Session\Session for adding some helpers
 *
 * Class Session
 *
 * @package Thelia\Core\HttpFoundation\Session
 *
 * Symfony\Component\HttpFoundation\Request
 */
class Session extends BaseSession
{
    // Lifetime, in seconds, of form error data
    const FORM_ERROR_LIFETIME_SECONDS = 60;
/*
    public function __construct(
        SessionStorageInterface $storage = null,
        AttributeBagInterface $attributes = null,
        FlashBagInterface $flashes = null
    ) {
        parent::__construct($storage, $attributes, $flashes);

        // Check for obsolete from error data
        $this->cleanOutdatedFormErrorInformation();
    }
*/
    /**
     * @param bool $forceDefault
     *
     * @return \Thelia\Model\Lang|null
     */
    public function getLang($forceDefault = true)
    {
        $lang = $this->get("thelia.current.lang");
        if (null === $lang && $forceDefault) {
            $lang = Lang::getDefaultLanguage();
        }

        return $lang;
    }

    public function setLang(Lang $lang)
    {
        $this->set("thelia.current.lang", $lang);

        return $this;
    }

    public function setCurrency(Currency $currency)
    {
        $this->set("thelia.current.currency", $currency);
    }

    /**
     * Return current currency
     *
     * @param bool $forceDefault If default currency forced
     *
     * @return Currency
     */
    public function getCurrency($forceDefault = true)
    {
        $currency = $this->get("thelia.current.currency");

        if (null === $currency && $forceDefault) {
            $currency = Currency::getDefaultCurrency();
        }

        return $currency;
    }

    // -- Admin lang and currency ----------------------------------------------

    public function getAdminEditionCurrency()
    {
        $currency = $this->get('thelia.admin.edition.currency', null);

        if (null === $currency) {
            $currency =  Currency::getDefaultCurrency();
        }

        return $currency;
    }

    public function setAdminEditionCurrency($currencyId)
    {
        $this->set('thelia.admin.edition.currency', $currencyId);

        return $this;
    }

    public function getAdminEditionLang()
    {
        $lang = $this->get('thelia.admin.edition.lang');

        if (null === $lang) {
            $lang =  Lang::getDefaultLanguage();
        }

        return $lang;
    }

    public function setAdminEditionLang($lang)
    {
        $this->set('thelia.admin.edition.lang', $lang);

        return $this;
    }

    // -- Customer user --------------------------------------------------------

    public function setCustomerUser(UserInterface $user)
    {
        $this->set('thelia.customer_user', $user);

        return $this;
    }

    /**
     * @return UserInterface|null the current front office user, or null if none is legged in
     */
    public function getCustomerUser()
    {
        return $this->get('thelia.customer_user');
    }

    public function clearCustomerUser()
    {
        return $this->remove('thelia.customer_user');
    }

    // -- Admin user -----------------------------------------------------------

    public function setAdminUser(UserInterface $user)
    {
        $this->set('thelia.admin_user', $user);

        return $this;
    }

    public function getAdminUser()
    {
        return $this->get('thelia.admin_user');
    }

    public function clearAdminUser()
    {
        return $this->remove('thelia.admin_user');
    }

    // -- Return page ----------------------------------------------------------

    public function setReturnToUrl($url)
    {
        $this->set('thelia.return_to_url', $url);

        return $this;
    }

    /**
     *
     * @return string the return-to URL, or the index page if none is defined.
     */
    public function getReturnToUrl()
    {
        return $this->get('thelia.return_to_url', URL::getInstance()->getIndexPage());
    }

    // -- Cart ------------------------------------------------------------------

    /**
     * Return the cart stored in the current session
     *
     * @param EventDispatcherInterface $dispatcher the event dispatcher, required if no cart is currently stored in the session
     *
     * @return Cart The cart in the current session .
     */
    public function getSessionCart(EventDispatcherInterface $dispatcher = null)
    {
        $cart_id = $this->get("thelia.cart_id", null);
        if (null !== $cart_id) {
            $cart = CartQuery::create()->findPk($cart_id);
        } else {
            $cart = null;
        }

        // If we do not have a cart, or if the current cart is nor valid
        // restore it from the cart cookie, or create a new one
        if (null === $cart || ! $this->isValidCart($cart)) {
            // A dispatcher is required here. If we do not have it, throw an exception
            // This is a temporary workaround to ensure backward compatibility with getCart(),
            // When genCart() will be removed, this check should be removed, and  $dispatcher should become
            // a required parameter.

            if (null == $dispatcher) {
                throw new \InvalidArgumentException(
                    "In this context (no cart in session), an EventDispatcher should be provided to Session::getSessionCart()."
                );
            }

            $cartEvent = new CartRestoreEvent();

            if (null !== $cart) {
                $cartEvent->setCart($cart);
            }

            $dispatcher->dispatch(TheliaEvents::CART_RESTORE_CURRENT, $cartEvent);

            if (null === $cart = $cartEvent->getCart()) {
                throw new \LogicException(
                    "Unable to get a Cart."
                );
            }

            // Store the cart ID.
            $this->set("thelia.cart_id", $cart->getId());
        }

        return $cart;
    }

    /**
     * Clear the current session cart, and store a new, empty one in the session.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function clearSessionCart(EventDispatcherInterface $dispatcher)
    {
        $event = new CartCreateEvent();

        $dispatcher->dispatch(TheliaEvents::CART_CREATE_NEW, $event);

        if (null === $cart = $event->getCart()) {
            throw new \LogicException(
                "Unable to get a new empty Cart."
            );
        }

        // Store the cart ID.
        $this->set("thelia.cart_id", $cart->getId());
    }

    /**
     * Return cart if it exists and is valid (checking customer)
     *
     * @return \Thelia\Model\Cart|null
     * @deprecated use getSessionCart() instead
     */
    public function getCart()
    {
        trigger_error(
            'getCart is deprecated, please use getSessionCart method instead',
            E_USER_DEPRECATED
        );

        return $this->getSessionCart(null);
    }

    /**
     * A cart is valid if its customer ID is the same as the current logged in user
     *
     * @param Cart $cart The cart to check
     *
     * @return bool true if the cart is valid, false otherwise
     */
    protected function isValidCart(Cart $cart)
    {
        $customer = $this->getCustomerUser();

        return (null !== $customer && $cart->getCustomerId() == $customer->getId())
            ||
        (null === $customer && $cart->getCustomerId() === null);
    }

    // -- Order ------------------------------------------------------------------

    public function setOrder(Order $order)
    {
        $this->set("thelia.order", $order);

        return $this;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        $order = $this->get("thelia.order");

        if (null === $order) {
            $order = new Order();
            $this->setOrder($order);
        }

        return $order;
    }

    /**
     * Set consumed coupons by the Customer
     *
     * @param array $couponsCode An array of Coupon code
     *
     * @return $this
     */
    public function setConsumedCoupons(array $couponsCode)
    {
        $this->set('thelia.consumed_coupons', $couponsCode);

        return $this;
    }

    /**
     * Get Customer consumed coupons
     *
     * @return array $couponsCode An array of Coupon code
     */
    public function getConsumedCoupons()
    {
        return $this->get('thelia.consumed_coupons', array());
    }

    /**
     * Save form error information, to allow error processing even after a redirection.
     * The data is stored during a limited time (60 seconds), to prevent retaining outdated information.
     *
     * @param string $formName identifier of the form (probably the class name)
     * @param array $formData the form data to save
     * @return $this
     */
    public function addFormErrorInformation($formName, $formData)
    {
        $formErrorInformation = $this->get('thelia.form-errors', []);

        // Add new error information
        $formErrorInformation[$formName] = [
            'timestamp' => time(),
            'data'      => $formData
        ];

        $this->set('thelia.form-errors', $formErrorInformation);

        return $this;
    }

    /**
     * Get form error data from the saved information.
     *
     * @param string $formName the form name, as passed to addSerializedFormData()
     * @return array|null
     */
    public function getFormErrorInformation($formName)
    {
        $formErrorInformation = $this->get('thelia.form-errors', []);

        if (isset($formErrorInformation[$formName])) {
            return $formErrorInformation[$formName]['data'];
        }

        return null;
    }

    /**
     * Remove from thelia.form-errors array the obsxolete form error information.
     */
    protected function cleanOutdatedFormErrorInformation()
    {
        $formErrorInformation = $this->get('thelia.form-errors', []);

        $now = time();

        // Cleanup obsolete form information, and try to find the form data
        foreach ($formErrorInformation as $name => $formData) {
            if ($now - $formData['timestamp'] > self::FORM_ERROR_LIFETIME_SECONDS) {
                unset($formErrorInformation[$name]);
            }
        }

        $this->set('thelia.form-errors', $formErrorInformation);
    }
}
