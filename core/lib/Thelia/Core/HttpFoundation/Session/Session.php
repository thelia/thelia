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
    // -- Language ------------------------------------------------------------

    public function getLocale()
    {
        return $this->get("locale", "en_US");
    }

    public function setLocale($locale)
    {
        $this->set("locale", $locale);

        return $this;
    }

    /**
     * @return \Thelia\Model\Lang|null
     */
    public function getLang()
    {
        return $this->get("lang");
    }

    public function setLang(Lang $lang)
    {
        $this->set("lang", $lang);

        return $this;
    }

    public function getLangId()
    {
        return $this->get("lang_id", Lang::getDefaultLanguage()->getId());
    }

    public function setLangId($langId)
    {
        $this->set("lang_id", $langId);

        return $this;
    }

    public function getAdminEditionLangId()
    {
        return $this->get('admin.edition_language', Lang::getDefaultLanguage()->getId());
    }

    public function setAdminEditionLangId($langId)
    {
        $this->set('admin.edition_language', $langId);

        return $this;
    }

    // -- Customer user --------------------------------------------------------

    public function setCustomerUser(UserInterface $user)
    {
        $this->set('customer_user', $user);
        return $this;
    }

    public function getCustomerUser()
    {
        return $this->get('customer_user');
    }

    public function clearCustomerUser()
    {
        return $this->remove('customer_user');
    }

    // -- Admin user -----------------------------------------------------------

    public function setAdminUser(UserInterface $user)
    {
        $this->set('admin_user', $user);
        return $this;
    }

    public function getAdminUser()
    {
        return $this->get('admin_user');
    }

    public function clearAdminUser()
    {
        return $this->remove('admin_user');
    }

    // -- Return page ----------------------------------------------------------

    public function setReturnToUrl($url)
    {
        $this->set('return_to_url', $url);
        return $this;
    }

    /**
     *
     * @return the return-to URL, or the index page if none is defined.
     */
    public function getReturnToUrl()
    {
        return $this->get('return_to_url', URL::getIndexPage());
    }

    // -- Cart ------------------------------------------------------------------

    /**
     * return cart if exists and is valid (checking customer)
     *
     * @return \Thelia\Model\Cart|null
     */
    public function getCart()
    {
        $cart_id =  $this->get("cart_id");
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
     */
    public function setCart($cart_id)
    {
        $this->set("cart_id", $cart_id);
        return $this;
    }
}
