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

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\Order;

class OrderEvent extends ActionEvent
{
    protected $order = null;
    protected $placedOrder = null;
    protected $invoiceAddress = null;
    protected $deliveryAddress = null;
    protected $deliveryModule = null;
    protected $paymentModule = null;
    protected $postage = null;
    protected $ref = null;
    protected $status = null;
    protected $deliveryRef = null;

    protected $cartItemId = null;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->setOrder($order);
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @param null $cartItemId
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    /**
     * @return null
     */
    public function getCartItemId()
    {
        return $this->cartItemId;
    }

    /**
     * @param Order $order
     */
    public function setPlacedOrder(Order $order)
    {
        $this->placedOrder = $order;
    }

    /**
     * @param $address
     */
    public function setInvoiceAddress($address)
    {
        $this->invoiceAddress = $address;
    }

    /**
     * @param $address
     */
    public function setDeliveryAddress($address)
    {
        $this->deliveryAddress = $address;
    }

    /**
     * @param $module
     */
    public function setDeliveryModule($module)
    {
        $this->deliveryModule = $module;
    }

    /**
     * @param $module
     */
    public function setPaymentModule($module)
    {
        $this->paymentModule = $module;
    }

    /**
     * @param $postage
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;
    }

    /**
     * @param $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
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

    /**
     * @param  Response $response
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function hasResponse()
    {
        return null !== $this->response;
    }
}
