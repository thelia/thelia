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

/**
 * Class OrderEvent
 * @package Thelia\Core\Event\Order
 */
class OrderEvent extends ActionEvent
{
    /** @var Order */
    protected $order = null;

    /** @var Order */
    protected $placedOrder = null;

    /** @var null|int */
    protected $invoiceAddress = null;

    /** @var null|int */
    protected $deliveryAddress = null;

    /** @var null|int */
    protected $deliveryModule = null;

    /** @var null|int */
    protected $paymentModule = null;

    /** @var null|float */
    protected $postage = null;

    /** @var float */
    protected $postageTax = 0.0;

    /** @var null|string */
    protected $postageTaxRuleTitle = null;

    /** @var null|string */
    protected $ref = null;

    /** @var null|int */
    protected $status = null;

    /** @var null|string */
    protected $deliveryRef = null;

    /** @var null|int */
    protected $cartItemId = null;

    /** @var null|string  */
    protected $transactionRef = null;

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
     * @return $this
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param int $cartItemId
     * @return $this
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
     * @return $this
     */
    public function setPlacedOrder(Order $order)
    {
        $this->placedOrder = $order;

        return $this;
    }

    /**
     * @param int $address an address ID
     * @return $this
     */
    public function setInvoiceAddress($address)
    {
        $this->invoiceAddress = $address;

        return $this;
    }

    /**
     * @param int $address an address ID
     * @return $this
     */
    public function setDeliveryAddress($address)
    {
        $this->deliveryAddress = $address;

        return $this;
    }

    /**
     * @param int $module a delivery module ID
     * @return $this
     */
    public function setDeliveryModule($module)
    {
        $this->deliveryModule = $module;

        return $this;
    }

    /**
     * @param int $module a payment module ID
     * @return $this
     */
    public function setPaymentModule($module)
    {
        $this->paymentModule = $module;

        return $this;
    }

    /**
     * @param double  $postage the postage amount
     * @return $this
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;

        return $this;
    }

    /**
     * @param string $ref the order reference
     * @return $this
     */
    public function setRef($ref)
    {
        $this->ref = $ref;

        return $this;
    }

    /**
     * @param int $status the order status ID
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param string $deliveryRef the delivery reference
     * @return $this
     */
    public function setDeliveryRef($deliveryRef)
    {
        $this->deliveryRef = $deliveryRef;

        return $this;
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

    /**
     * @return null
     */
    public function getPostageTax()
    {
        return $this->postageTax;
    }

    /**
     * @param null $postageTax
     * @return $this
     */
    public function setPostageTax($postageTax)
    {
        $this->postageTax = $postageTax;

        return $this;
    }

    /**
     * @return null
     */
    public function getPostageTaxRuleTitle()
    {
        return $this->postageTaxRuleTitle;
    }

    /**
     * @param null $postageTaxRuleTitle
     * @return $this
     */
    public function setPostageTaxRuleTitle($postageTaxRuleTitle)
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;

        return $this;
    }

    /**
     * @since 2.4.0
     * @return null|string
     */
    public function getTransactionRef()
    {
        return $this->transactionRef;
    }

    /**
     * @since 2.4.0
     * @param null|string $transactionRef
     * @return $this
     */
    public function setTransactionRef($transactionRef)
    {
        $this->transactionRef = $transactionRef;
        return $this;
    }
}
