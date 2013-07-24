<?php

namespace Thelia\Model;

use Thelia\Model\Base\Cart as BaseCart;

class Cart extends BaseCart
{

    public function getCart(Request $request)
    {
        if ($request->cookies->has("thelia_cart")) {
            //le cookie de panier existe, on le récupère
            $cookie = $request->cookies->get("thelia_cart");

            $cart = CartQuery::create()->findOneByToken($cookie);

            if ($cart) {
                //le panier existe en base
                $customer = $request->getSession()->getCustomerUser();

                if ($customer) {
                    if($cart->getCustomerId() != $customer->getId()) {
                        //le customer du panier n'est pas le mm que celui connecté, il faut cloner le panier sans le customer_id
                        $cart = $this->duplicate($customer);
                    }
                } else {
                    if ($cart->getCustomerId() != null) {
                        //il faut dupliquer le panier sans le customer_id
                        $cart = $this->duplicate();
                    }
                }

            } else {
                $cart = $this->createCart();
            }
        } else {
            //le cookie de panier n'existe pas, il va falloir le créer et faire un enregistrement en base.
            $cart = $this->createCart();
        }

        return $cart;
    }

    public function createCart()
    {

    }

    public function duplicate(Customer $customer = null)
    {
        $cartItems = $this->getCartItems();

        $cart = new Cart();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($this->generateCookie());

        if ($customer){
            $cart->setCustomer($customer);
        }
        // TODO : set current Currency
        //$cart->setCurrency()
        $cart->save();

        foreach ($cartItems as $cartItem){
            $item = new CartItem();
            $item->setCart($cart);
            $item->setProductId($cartItem->getProductId());
            $item->setQuantity($cartItem->getQuantity());
            $item->save();
        }

        return $cart;
    }

    public function generateCookie()
    {
        $id = uniqid('', true);

        setcookie("thelia_cart", $id, time());

        return $id;
    }

    public function addItem()
    {

    }
}
