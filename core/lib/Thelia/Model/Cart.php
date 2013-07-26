<?php

namespace Thelia\Model;

use Thelia\Model\Base\Cart as BaseCart;

class Cart extends BaseCart
{

    public function duplicate($token, Customer $customer = null)
    {
        $cartItems = $this->getCartItems();

        $cart = new Cart();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($token);

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
}
