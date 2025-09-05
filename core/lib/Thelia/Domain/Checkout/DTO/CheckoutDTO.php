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

namespace Thelia\Domain\Checkout\DTO;

use Thelia\Domain\Cart\DTO\CartDTOInterface;
use Thelia\Domain\Shared\Contract\DTOEventActionInterface;
use Thelia\Model\Cart;
use Thelia\Model\OrderPostage;

class CheckoutDTO implements DTOEventActionInterface, CartDTOInterface
{
    public function __construct(
        protected Cart $cart,
        protected ?int $deliveryModuleId = null,
        protected ?int $deliveryAddressId = null,
        protected ?int $invoiceAddressId = null,
        protected ?int $paymentModuleId = null,
        protected ?OrderPostage $postage = null,
        protected array $extendedData = [],
    ) {
        if (null === $this->deliveryAddressId) {
            $this->deliveryAddressId = $cart->getAddressDeliveryId();
        }
        if (null === $this->deliveryModuleId) {
            $this->deliveryModuleId = $cart->getDeliveryModuleId();
        }
        if (null === $this->invoiceAddressId) {
            $this->invoiceAddressId = $cart->getAddressInvoiceId();
        }
        if (null === $this->paymentModuleId) {
            $this->paymentModuleId = $cart->getPaymentModuleId();
        }
    }

    public function toArray(): array
    {
        return [
            'cart' => $this->cart,
            'delivery_module_id' => $this->deliveryModuleId,
            'delivery_address_id' => $this->deliveryAddressId,
            'invoice_address_id' => $this->invoiceAddressId,
            'payment_module_id' => $this->paymentModuleId,
            'postage' => $this->postage,
            'extended_data' => $this->extendedData,
        ];
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getDeliveryModuleId(): ?int
    {
        return $this->deliveryModuleId;
    }

    public function getDeliveryAddressId(): ?int
    {
        return $this->deliveryAddressId;
    }

    public function getInvoiceAddressId(): ?int
    {
        return $this->invoiceAddressId;
    }

    public function getPaymentModuleId(): ?int
    {
        return $this->paymentModuleId;
    }

    public function getPostage(): ?OrderPostage
    {
        return $this->postage;
    }

    public function getExtendedData(): array
    {
        return $this->extendedData;
    }
}
