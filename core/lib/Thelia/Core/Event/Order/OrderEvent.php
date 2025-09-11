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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

/**
 * Class OrderEvent.
 */
class OrderEvent extends ActionEvent
{
    protected ?Order $order;
    protected Order $placedOrder;
    protected ?int $invoiceAddress = null;
    protected ?int $deliveryAddress = null;
    protected ?int $deliveryModule = null;
    protected ?int $paymentModule = null;
    protected ?float $postage = null;
    protected float $postageTax = 0.0;
    protected ?string $postageTaxRuleTitle = null;
    protected ?string $ref = null;
    protected ?int $status = null;
    protected ?string $deliveryRef = null;
    protected ?int $cartItemId = null;
    protected ?string $transactionRef = null;
    protected ?Response $response = null;

    public function __construct(?Order $order)
    {
        $this->setOrder($order);
    }

    /**
     * @return $this
     */
    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCartItemId(?int $cartItemId): self
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    public function getCartItemId(): ?int
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
    public function setInvoiceAddress(int $address): self
    {
        $this->invoiceAddress = $address;

        return $this;
    }

    /**
     * @param int $address an address ID
     *
     * @return $this
     */
    public function setDeliveryAddress(int $address): self
    {
        $this->deliveryAddress = $address;

        return $this;
    }

    /**
     * @param int $module a delivery module ID
     *
     * @return $this
     */
    public function setDeliveryModule(int $module): self
    {
        $this->deliveryModule = $module;

        return $this;
    }

    /**
     * @param int $module a payment module ID
     *
     * @return $this
     */
    public function setPaymentModule(int $module): self
    {
        $this->paymentModule = $module;

        return $this;
    }

    /**
     * @param float $postage the postage amount
     *
     * @return $this
     */
    public function setPostage(float $postage): self
    {
        $this->postage = $postage;

        return $this;
    }

    /**
     * @param string $ref the order reference
     *
     * @return $this
     */
    public function setRef(string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    /**
     * @param int $status the order status ID
     *
     * @return $this
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param string $deliveryRef the delivery reference
     *
     * @return $this
     */
    public function setDeliveryRef(string $deliveryRef): self
    {
        $this->deliveryRef = $deliveryRef;

        return $this;
    }

    /**
     * @return Order the order
     */
    public function getOrder(): ?Order
    {
        return $this->order;
    }

    /**
     * @return Order the placed order, valid only after order payment
     *
     * @throws \LogicException if the method is called before payment
     */
    public function getPlacedOrder(): Order
    {
        return $this->placedOrder;
    }

    /**
     * @return int|null the invoice address ID
     */
    public function getInvoiceAddress(): ?int
    {
        return $this->invoiceAddress;
    }

    /**
     * @return int|null the delivery addres ID
     */
    public function getDeliveryAddress(): ?int
    {
        return $this->deliveryAddress;
    }

    /**
     * @return int|null the delivery module ID
     */
    public function getDeliveryModule(): ?int
    {
        return $this->deliveryModule;
    }

    /**
     * @return int|null the payment module ID
     */
    public function getPaymentModule(): ?int
    {
        return $this->paymentModule;
    }

    /**
     * @return float|null the postage amount
     */
    public function getPostage(): ?float
    {
        return $this->postage;
    }

    /**
     * @return string|null the order reference
     */
    public function getRef(): ?string
    {
        return $this->ref;
    }

    /**
     * @return int|null the order status ID
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @return string|null the delivery reference
     */
    public function getDeliveryRef(): ?string
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
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return bool true if this event has a payment request response
     */
    public function hasResponse(): bool
    {
        return $this->response instanceof Response;
    }

    public function getPostageTax(): float
    {
        return $this->postageTax;
    }

    /**
     * @return $this
     */
    public function setPostageTax(float $postageTax): self
    {
        $this->postageTax = $postageTax;

        return $this;
    }

    public function getPostageTaxRuleTitle(): ?string
    {
        return $this->postageTaxRuleTitle;
    }

    /**
     * @return $this
     */
    public function setPostageTaxRuleTitle(?string $postageTaxRuleTitle): self
    {
        $this->postageTaxRuleTitle = $postageTaxRuleTitle;

        return $this;
    }

    public function getTransactionRef(): ?string
    {
        return $this->transactionRef;
    }

    /**
     * @return $this
     */
    public function setTransactionRef(?string $transactionRef): self
    {
        $this->transactionRef = $transactionRef;

        return $this;
    }
}
