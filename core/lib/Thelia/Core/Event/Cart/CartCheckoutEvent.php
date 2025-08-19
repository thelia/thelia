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

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Cart;
use Thelia\Model\OrderPostage;

class CartCheckoutEvent extends ActionEvent
{
    protected Cart $cart;

    protected ?int $deliveryModuleId = null;
    protected ?int $deliveryAddressId = null;
    protected ?int $invoiceAddressId = null;

    protected ?int $paymentModuleId = null;

    protected ?OrderPostage $postage = null;

    protected array $extendedData = [];

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;

        $this->deliveryAddressId = $cart->getAddressDeliveryId();
        $this->deliveryModuleId = $cart->getDeliveryModuleId();
        $this->invoiceAddressId = $cart->getAddressInvoiceId();
        $this->paymentModuleId = $cart->getPaymentModuleId();
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getPostage(): ?OrderPostage
    {
        return $this->postage;
    }

    public function setPostage(?OrderPostage $postage): self
    {
        $this->postage = $postage;

        return $this;
    }

    public function getDeliveryModuleId(): ?int
    {
        return $this->deliveryModuleId;
    }

    public function setDeliveryModuleId(?int $deliveryModuleId): self
    {
        $this->deliveryModuleId = $deliveryModuleId;

        return $this;
    }

    public function getDeliveryAddressId(): ?int
    {
        return $this->deliveryAddressId;
    }

    public function setDeliveryAddressId(?int $deliveryAddressId): self
    {
        $this->deliveryAddressId = $deliveryAddressId;

        return $this;
    }

    public function getInvoiceAddressId(): ?int
    {
        return $this->invoiceAddressId;
    }

    public function setInvoiceAddressId(?int $invoiceAddressId): self
    {
        $this->invoiceAddressId = $invoiceAddressId;

        return $this;
    }

    public function getPaymentModuleId(): ?int
    {
        return $this->paymentModuleId;
    }

    public function setPaymentModuleId(?int $paymentModuleId): self
    {
        $this->paymentModuleId = $paymentModuleId;

        return $this;
    }

    public function getExtendedData(?string $key = null): ?array
    {
        return $key === null
            ? $this->extendedData
            : $this->extendedData[$key] ?? null;
    }

    public function setExtendedData(string $key, mixed $value): static
    {
        $this->extendedData[$key] = $value;

        return $this;
    }

    public function removeExtendedData(string $key): static
    {
        unset($this->extendedData[$key]);

        return $this;
    }
}
