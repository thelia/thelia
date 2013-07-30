<?php

namespace Thelia\Model;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Base\Cart as BaseCart;
use Thelia\Model\Base\StockQuery;

class Cart extends BaseCart
{

    protected $dispatcher;

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function duplicate($token, Customer $customer = null)
    {
        $cartItems = $this->getCartItems();

        $cart = new Cart();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($token);
        // TODO : set current Currency
        $cart->setCurrencyId($this->getCurrencyId());

        if ($customer){
            $cart->setCustomer($customer);
        }

        $cart->save();

        foreach ($cartItems as $cartItem){

            $product = $cartItem->getProduct();
            $stock = $cartItem->getStock();
            if ($product && $stock && $product->getVisible() == 1 && $stock->getQuantity() > $cartItem->getQuantity()) {

                $item = new CartItem();
                $item->setCart($cart);
                $item->setProductId($cartItem->getProductId());
                $item->setQuantity($cartItem->getQuantity());
                $item->save();
            }

        }

        return $cart;
    }

    protected function dispatchEvent($name)
    {

    }
}
