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

namespace Thelia\Tests\Unit\Domain\Cart;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Model\Cart;

class CartFacadeTest extends TestCase
{
    public function testCartItemAddDTOToArray(): void
    {
        $cart = $this->createCartMock(1);

        $dto = new CartItemAddDTO(
            cart: $cart,
            productId: 10,
            productSaleElementId: 20,
            quantity: 3,
            append: true,
            newness: false,
        );

        $array = $dto->toArray();

        $this->assertSame($cart, $array['cart']);
        $this->assertSame(10, $array['productId']);
        $this->assertSame(20, $array['productSaleElementsId']);
        $this->assertSame(3, $array['quantity']);
        $this->assertTrue($array['append']);
        $this->assertFalse($array['newness']);
    }

    public function testCartItemAddDTODefaultValues(): void
    {
        $cart = $this->createCartMock(1);

        $dto = new CartItemAddDTO(
            cart: $cart,
            productId: 10,
            productSaleElementId: 20,
        );

        $this->assertSame(1, $dto->toArray()['quantity']);
        $this->assertTrue($dto->toArray()['append']);
        $this->assertTrue($dto->toArray()['newness']);
    }

    public function testCartItemAddDTOGetCart(): void
    {
        $cart = $this->createCartMock(5);

        $dto = new CartItemAddDTO(
            cart: $cart,
            productId: 10,
            productSaleElementId: 20,
        );

        $this->assertSame($cart, $dto->getCart());
    }

    public function testCartItemDeleteDTOToArray(): void
    {
        $cart = $this->createCartMock(1);

        $dto = new CartItemDeleteDTO(
            cart: $cart,
            cartItemId: 42,
        );

        $array = $dto->toArray();

        $this->assertSame($cart, $array['cart']);
        $this->assertSame(42, $array['cart_item_id']);
    }

    public function testCartItemDeleteDTOGetCart(): void
    {
        $cart = $this->createCartMock(3);

        $dto = new CartItemDeleteDTO(
            cart: $cart,
            cartItemId: 42,
        );

        $this->assertSame($cart, $dto->getCart());
    }

    public function testCartItemUpdateQuantityDTOToArray(): void
    {
        $cart = $this->createCartMock(1);

        $dto = new CartItemUpdateQuantityDTO(
            cart: $cart,
            cartItemId: 42,
            quantity: 5,
        );

        $array = $dto->toArray();

        $this->assertSame($cart, $array['cart']);
        $this->assertSame(42, $array['cart_item_id']);
        $this->assertSame(5, $array['quantity']);
    }

    public function testCartItemUpdateQuantityDTOGetCart(): void
    {
        $cart = $this->createCartMock(7);

        $dto = new CartItemUpdateQuantityDTO(
            cart: $cart,
            cartItemId: 42,
            quantity: 10,
        );

        $this->assertSame($cart, $dto->getCart());
    }

    private function createCartMock(int $id): MockObject&Cart
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getId')->willReturn($id);

        return $cart;
    }
}
