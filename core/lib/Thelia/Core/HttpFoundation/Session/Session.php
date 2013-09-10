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
     * @return \Thelia\Model\Lang|null
     */
    public function getLang($forceDefault = true)
    {
        return $this->get("thelia.current.lang", $forceDefault ? Lang::getDefaultLanguage():null);
    }

    public function setLang(Lang $lang)
    {
        $this->set("thelia.current.lang", $lang);

        return $this;
    }

    public function getAdminEditionLang()
    {
        return $this->get('thelia.admin.edition.lang', Lang::getDefaultLanguage());
    }

    public function setAdminEditionLang($langId)
    {
        $this->set('thelia.admin.edition.lang', $langId);

        return $this;
    }

    public function setCurrency(Currency $currency)
    {
        $this->set("thelia.current.currency", $currency);
    }

    public function getCurrency($forceDefault = true)
    {
        return $this->get("thelia.current.currency", $forceDefault ? Currency::getDefaultCurrency():null);
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
            try {
                $this->verifyValidCart($cart);
            } catch (InvalidCartException $e) {
                $cart = null;
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

    /**
     * assign delivery id in session
     *
     * @param $delivery_id
     * @return $this
     */
    public function setDelivery($delivery_id)
    {
        $this->set("thelia.delivery_id", $delivery_id);
        return $this;
    }

    public function getDelivery()
    {
        return $this->get("thelia.delivery_id");
    }
}