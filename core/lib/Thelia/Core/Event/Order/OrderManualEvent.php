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

namespace Thelia\Core\Event\Order;

use Thelia\Model\Order;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

class OrderManualEvent extends OrderEvent
{
    protected $currency = null;
    protected $lang = null;
    protected $cart = null;
    protected $customer = null;

    /**
     * @param Order $order
     */
    public function __construct(Order $order, Currency $currency, Lang $lang, Cart $cart, Customer $customer)
    {
        $this
            ->setOrder($order)
            ->setCurrency($currency)
            ->setLang($lang)
            ->setCart($cart)
            ->setCustomer($customer)
        ;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param Order $order
     */
    public function setPlacedOrder(Order $order)
    {
        $this->placedOrder = $order;

        return $this;
    }

    /**
     * @param $address
     */
    public function setInvoiceAddress($address)
    {
        $this->invoiceAddress = $address;

        return $this;
    }

    /**
     * @param $address
     */
    public function setDeliveryAddress($address)
    {
        $this->deliveryAddress = $address;

        return $this;
    }

    /**
     * @param $module
     */
    public function setDeliveryModule($module)
    {
        $this->deliveryModule = $module;

        return $this;
    }

    /**
     * @param $module
     */
    public function setPaymentModule($module)
    {
        $this->paymentModule = $module;

        return $this;
    }

    /**
     * @param $postage
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;

        return $this;
    }

    /**
     * @param $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;

        return $this;
    }

    /**
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param $deliveryRef
     */
    public function setDeliveryRef($deliveryRef)
    {
        $this->deliveryRef = $deliveryRef;
    }

    /**
     * @return null|Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return null|Order
     */
    public function getPlacedOrder()
    {
        return $this->placedOrder;
    }

    /**
     * @return null|int
     */
    public function getInvoiceAddress()
    {
        return $this->invoiceAddress;
    }

    /**
     * @return null|int
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    /**
     * @return null|int
     */
    public function getDeliveryModule()
    {
        return $this->deliveryModule;
    }

    /**
     * @return null|int
     */
    public function getPaymentModule()
    {
        return $this->paymentModule;
    }

    /**
     * @return null|int
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * @return null|int
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @return null|int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return null|string
     */
    public function getDeliveryRef()
    {
        return $this->deliveryRef;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    public function getCart()
    {
        return $this->cart;
    }

    public function setCart($cart)
    {
        $this->cart = $cart;

        return $this;
    }

    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer($customer)
    {
        $this->customer = $customer;

        return $this;
    }

}
