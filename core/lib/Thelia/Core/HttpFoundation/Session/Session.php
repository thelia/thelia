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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Session as BaseSession;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Exception\InvalidCartException;
use Thelia\Model\CartQuery;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Order;
use Thelia\Tools\URL;
use Thelia\Model\Lang;

/**
 *
 * extends mfony\Component\HttpFoundation\Session\Session for adding some helpers
 *
 * Class Session
 * @package Thelia\Core\HttpFoundation\Session
 * Symfony\Component\HttpFoundation\Request
 */
class Session extends BaseSession
{
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
     * @return the return-to URL, or the index page if none is defined.
     */
    public function getReturnToUrl()
    {
        return $this->get('thelia.return_to_url', URL::getInstance()->getIndexPage());
    }

    // -- Cart ------------------------------------------------------------------

    /**
     * return cart if exists and is valid (checking customer)
     *
     * @return \Thelia\Model\Cart|null
     */
    public function getCart()
    {
        $cart_id =  $this->get("thelia.cart_id");
        $cart = null;
        if ($cart_id) {
            $cart = CartQuery::create()->findPk($cart_id);
            if ($cart) {
                try {
                    $this->verifyValidCart($cart);
                } catch (InvalidCartException $e) {
                    $cart = null;
                }
            }
        }

        return $cart;
    }

    /**
     *
     *
     * @param  \Thelia\Model\Cart                     $cart
     * @throws \Thelia\Exception\InvalidCartException
     */
    protected function verifyValidCart(Cart $cart)
    {
        $customer = $this->getCustomerUser();
        if ($customer && $cart->getCustomerId() != $customer->getId()) {
            throw new InvalidCartException("customer in session and customer_id in cart are not the same");
        } elseif ($customer === null && $cart->getCustomerId() !== null) {
            throw new InvalidCartException("Customer exists in cart and not in session");
        }
    }

    /**
     * assign cart id in session
     *
     * @param $cart_id
     * @return $this
     */
    public function setCart($cart_id)
    {
        $this->set("thelia.cart_id", $cart_id);

        return $this;
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
        return $this->get("thelia.order");
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
        return $this->get('thelia.consumed_coupons');
    }
}
