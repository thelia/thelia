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

namespace Thelia\Cart;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\CartQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Cart\CartEvent;

/**
 * managed cart
 *
 * Trait CartTrait
 * @package Thelia\Cart
 * @author Manuel Raynaud <manu@thelia.net>
 */
trait CartTrait
{
    /**
     *
     * search if cart already exists in session. If not try to create a new one or duplicate an old one.
     *
     * @param  EventDispatcherInterface                  $dispatcher the event dispatcher
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @return \Thelia\Model\Cart
     */
    public function getCart(EventDispatcherInterface $dispatcher, Request $request)
    {
        $session = $request->getSession();

        if (null !== $cart = $session->getCart()) {
            return $cart;
        }

        if ($request->cookies->has("thelia_cart")) {
            //le cookie de panier existe, on le récupère
            $token = $request->cookies->get("thelia_cart");

            $cart = CartQuery::create()->findOneByToken($token);

            if ($cart) {
                //le panier existe en base
                $customer = $session->getCustomerUser();

                if ($customer) {
                    if ($cart->getCustomerId() != $customer->getId()) {
                        //le customer du panier n'est pas le mm que celui connecté, il faut cloner le panier sans le customer_id
                        $cart = $this->duplicateCart($dispatcher, $cart, $session, $customer);
                    }
                } else {
                    if ($cart->getCustomerId() != null) {
                        //il faut dupliquer le panier sans le customer_id
                        $cart = $this->duplicateCart($dispatcher, $cart, $session);
                    }
                }
            } else {
                $cart = $this->createCart($session);
            }
        } else {
            //le cookie de panier n'existe pas, il va falloir le créer et faire un enregistrement en base.
            $cart = $this->createCart($session);
        }
        $session->setCart($cart->getId());

        return $cart;
    }

    /**
     * @param  \Thelia\Core\HttpFoundation\Session\Session $session
     * @return \Thelia\Model\Cart
     */
    protected function createCart(Session $session)
    {
        $cart = new CartModel();
        $cart->setToken($this->generateCookie($session));
        $cart->setCurrency($session->getCurrency(true));

        if (null !== $customer = $session->getCustomerUser()) {
            $cart->setCustomer($customer);
        }

        $cart->save();

        $session->setCart($cart->getId());

        return $cart;
    }

    /**
     * try to duplicate existing Cart. Customer is here to determine if this cart belong to him.
     *
     * @param  \Thelia\Model\Cart                          $cart
     * @param  \Thelia\Core\HttpFoundation\Session\Session $session
     * @param  \Thelia\Model\Customer                      $customer
     * @return \Thelia\Model\Cart
     */
    protected function duplicateCart(EventDispatcherInterface $dispatcher, CartModel $cart, Session $session, Customer $customer = null)
    {
        $currency = $session->getCurrency();
        $newCart = $cart->duplicate($this->generateCookie($session), $customer, $currency, $dispatcher);
        $session->setCart($newCart->getId());

        $cartEvent = new CartEvent($newCart);

        $dispatcher->dispatch(TheliaEvents::CART_DUPLICATE, $cartEvent);

        return $cartEvent->getCart();
    }

    protected function generateCookie(Session $session)
    {
        $id = null;
        if (ConfigQuery::read("cart.session_only", 0) == 0) {
            $id = uniqid('', true);
            $session->set('cart_use_cookie', $id);
        }

        return $id;
    }
}
