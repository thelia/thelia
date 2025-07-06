<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Core\Event\Order;

use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

/**
 * Class OrderEvent.
 */
class OrderEvent extends ActionEvent
{
    /** @var Order */
    protected $order;

    /** @var Order */
    protected $placedOrder;

    /** @var int|null */
    protected $invoiceAddress;

    /** @var int|null */
    protected $deliveryAddress;

    /** @var int|null */
    protected $deliveryModule;

    /** @var int|null */
    protected $paymentModule;

    /** @var float|null */
    protected $postage;

    /** @var float */
    protected $postageTax = 0.0;

    /** @var string|null */
    protected $postageTaxRuleTitle;

    /** @var string|null */
    protected $ref;

    /** @var int|null */
    protected $status;

    /** @var string|null */
    protected $deliveryRef;

    /** @var int|null */
    protected $cartItemId;

    /** @var string|null */
    protected $transactionRef;

    /**
     * @var Response
     */
    protected $response;

    public function __construct(Order $order)
    {
        $this->setOrder($order);
    }

    /**
     * @return $this
     */
    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param int $cartItemId
     *
     * @return $this
     */
    public function setCartItemId($cartItemId): self
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
     * @return $this
     */
    public function setPlacedOrder(Order $order): self
    {
        $this->placedOrder = $order;

        return $this;
    }

    /**
     * @param int $address an address ID
     *
     * @return $this
     */
    public function setInvoiceAddress($address): self
    {
        $this->invoiceAddress = $address;

        return $this;
    }

    /**
     * @param int $address an address ID
     *
     * @return $this
     */
    public function setDeliveryAddress($address): self
    {
        $this->deliveryAddress = $address;

        return $this;
    }

    /**
     * @param int $module a delivery module ID
     *
     * @return $this
     */
    public function setDeliveryModule($module): self
    {
        $this->deliveryModule = $module;

        return $this;
    }

    /**
     * @param int $module a payment module ID
     *
     * @return $this
     */
    public function setPaymentModule($module): self
    {
        $this->paymentModule = $module;

        return $this;
    }

    /**
     * @param float $postage the postage amount
     *
     * @return $this
     */
    public function setPostage($postage): self
    {
        $this->postage = $postage;

        return $this;
    }

    /**
     * @param string $ref the order reference
     *
     * @return $this
     */
    public function setRef($ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    /**
     * @param int $status the order status ID
     *
     * @return $this
     */
    public function setStatus($status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param string $deliveryRef the delivery reference
     *
     * @return $this
     */
    public function setDeliveryRef($deliveryRef): self
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
     * @throws LogicException if the method is called before payment
     *
     * @return Order the placed order, valid only after order payment
     */
    public function getPlacedOrder()
    {
        if (null === $this->placedOrder) {
            throw new LogicException('The placed order is defined only after dispatching of the ORDER_PAY event');
        }

        return $this->placedOrder;
    }

    /**
     * @return int|null the invoice address ID
     */
    public function getInvoiceAddress()
    {
        return $this->invoiceAddress;
    }

    /**
     * @return int|null the delivery addres ID
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    /**
     * @return int|null the delivery module ID
     */
    public function getDeliveryModule()
    {
        return $this->deliveryModule;
    }

    /**
     * @return int|null the payment module ID
     */
    public function getPaymentModule()
    {
        return $this->paymentModule;
    }

    /**
     * @return float|null the postage amount
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * @return string|null the order reference
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @return int|null the order status ID
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string|null the delivery reference
     */
    public function getDeliveryRef()
    {
        return $this->deliveryRef;
    }

    /**
     * @param Response $response the payment request response
     *
     * @return $this
     */
    public function setResponse(Response $response): self
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
    public function hasResponse(): bool
    {
        return null !== $this->response;
    }

    public function getPostageTax()
    {
        return $this->postageTax;
    }

    /**
     * @return $this
     */
    public function setPostageTax($postageTax): self
    {
        $this->postageTax = $postageTax;

        return $this;
    }

    public function getPostageTaxRuleTitle()
    {
        return $this->postageTaxRuleTitle;
    }

    /**
     * @return $this
     */
    public function setPostageTaxRuleTitle($postageTaxRuleTitle): self
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;

        return $this;
    }

    /**
     * @since 2.4.0
     *
     * @return string|null
     */
    public function getTransactionRef()
    {
        return $this->transactionRef;
    }

    /**
     * @since 2.4.0
     *
     * @param string|null $transactionRef
     *
     * @return $this
     */
    public function setTransactionRef($transactionRef): self
    {
        $this->transactionRef = $transactionRef;

        return $this;
    }
}
