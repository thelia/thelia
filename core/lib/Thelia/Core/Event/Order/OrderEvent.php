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

namespace Thelia\Core\Event\Order;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
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
     * @param int $cartItemId
     */
    public function setCartItemId($cartItemId)
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    /**
     * @return int
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
     * @param int $address an address ID
     */
    public function setInvoiceAddress($address)
    {
        $this->invoiceAddress = $address;
    }

    /**
     * @param int $address an address ID
     */
    public function setDeliveryAddress($address)
    {
        $this->deliveryAddress = $address;
    }

    /**
     * @param int $module a delivery module ID
     */
    public function setDeliveryModule($module)
    {
        $this->deliveryModule = $module;
    }

    /**
     * @param int $module a payment module ID
     */
    public function setPaymentModule($module)
    {
        $this->paymentModule = $module;
    }

    /**
     * @param double  $postage the postage amount
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;
    }

    /**
     * @param string $ref the order reference
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @param int $status the order status ID
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @param string $deliveryRef the delivery reference
     */
    public function setDeliveryRef($deliveryRef)
    {
        $this->deliveryRef = $deliveryRef;
    }

    /**
     * @return Order the order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return Order the placed order, valid only after order payment
     * @throws \LogicException if the method is called before payment
     */
    public function getPlacedOrder()
    {
        if (null === $this->placedOrder) {
            throw new \LogicException("The placed order is defined only after dispatching of the ORDER_PAY event");
        }

        return $this->placedOrder;
    }

    /**
     * @return null|int the invoice address ID
     */
    public function getInvoiceAddress()
    {
        return $this->invoiceAddress;
    }

    /**
     * @return null|int the delivery addres ID
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    /**
     * @return null|int the delivery module ID
     */
    public function getDeliveryModule()
    {
        return $this->deliveryModule;
    }

    /**
     * @return null|int the payment module ID
     */
    public function getPaymentModule()
    {
        return $this->paymentModule;
    }

    /**
     * @return null|double the postage amount
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * @return null|string the order reference
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @return null|int the order status ID
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return null|string the delivery reference
     */
    public function getDeliveryRef()
    {
        return $this->deliveryRef;
    }

    /**
     * @param  Response $response the payment request response
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return Response the payment request response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return bool true if this event has a payment request response
     */
    public function hasResponse()
    {
        return null !== $this->response;
    }
}