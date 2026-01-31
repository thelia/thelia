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

namespace Thelia\Tests\Unit\Domain\Checkout;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Model\Cart;
use Thelia\Model\OrderPostage;

class CheckoutFacadeTest extends TestCase
{
    public function testCheckoutDTOToArray(): void
    {
        $cart = $this->createCartMock(1);
        $postage = $this->createMock(OrderPostage::class);

        $dto = new CheckoutDTO(
            cart: $cart,
            deliveryModuleId: 10,
            deliveryAddressId: 20,
            invoiceAddressId: 30,
            paymentModuleId: 40,
            postage: $postage,
            extendedData: ['key' => 'value'],
        );

        $array = $dto->toArray();

        $this->assertSame($cart, $array['cart']);
        $this->assertSame(10, $array['delivery_module_id']);
        $this->assertSame(20, $array['delivery_address_id']);
        $this->assertSame(30, $array['invoice_address_id']);
        $this->assertSame(40, $array['payment_module_id']);
        $this->assertSame($postage, $array['postage']);
        $this->assertSame(['key' => 'value'], $array['extended_data']);
    }

    public function testCheckoutDTOGetters(): void
    {
        $cart = $this->createCartMock(1);
        $postage = $this->createMock(OrderPostage::class);

        $dto = new CheckoutDTO(
            cart: $cart,
            deliveryModuleId: 10,
            deliveryAddressId: 20,
            invoiceAddressId: 30,
            paymentModuleId: 40,
            postage: $postage,
            extendedData: ['foo' => 'bar'],
        );

        $this->assertSame($cart, $dto->getCart());
        $this->assertSame(10, $dto->getDeliveryModuleId());
        $this->assertSame(20, $dto->getDeliveryAddressId());
        $this->assertSame(30, $dto->getInvoiceAddressId());
        $this->assertSame(40, $dto->getPaymentModuleId());
        $this->assertSame($postage, $dto->getPostage());
        $this->assertSame(['foo' => 'bar'], $dto->getExtendedData());
    }

    public function testCheckoutDTOFallsBackToCartValues(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getId')->willReturn(1);
        $cart->method('getAddressDeliveryId')->willReturn(100);
        $cart->method('getAddressInvoiceId')->willReturn(200);
        $cart->method('getDeliveryModuleId')->willReturn(300);
        $cart->method('getPaymentModuleId')->willReturn(400);

        $dto = new CheckoutDTO(cart: $cart);

        $this->assertSame(100, $dto->getDeliveryAddressId());
        $this->assertSame(200, $dto->getInvoiceAddressId());
        $this->assertSame(300, $dto->getDeliveryModuleId());
        $this->assertSame(400, $dto->getPaymentModuleId());
    }

    public function testCheckoutDTOExplicitValuesOverrideCart(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getId')->willReturn(1);
        $cart->method('getAddressDeliveryId')->willReturn(100);
        $cart->method('getAddressInvoiceId')->willReturn(200);
        $cart->method('getDeliveryModuleId')->willReturn(300);
        $cart->method('getPaymentModuleId')->willReturn(400);

        $dto = new CheckoutDTO(
            cart: $cart,
            deliveryModuleId: 999,
            deliveryAddressId: 888,
            invoiceAddressId: 777,
            paymentModuleId: 666,
        );

        $this->assertSame(888, $dto->getDeliveryAddressId());
        $this->assertSame(777, $dto->getInvoiceAddressId());
        $this->assertSame(999, $dto->getDeliveryModuleId());
        $this->assertSame(666, $dto->getPaymentModuleId());
    }

    public function testCheckoutDTODefaultExtendedData(): void
    {
        $cart = $this->createCartMock(1);

        $dto = new CheckoutDTO(cart: $cart);

        $this->assertSame([], $dto->getExtendedData());
        $this->assertNull($dto->getPostage());
    }

    private function createCartMock(int $id): MockObject&Cart
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getId')->willReturn($id);
        $cart->method('getAddressDeliveryId')->willReturn(null);
        $cart->method('getAddressInvoiceId')->willReturn(null);
        $cart->method('getDeliveryModuleId')->willReturn(null);
        $cart->method('getPaymentModuleId')->willReturn(null);

        return $cart;
    }
}
