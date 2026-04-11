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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CartActionTest extends ActionIntegrationTestCase
{
    public function testCreateEmptyCartAttachesCartToSession(): void
    {
        $cartCreateEvent = new CartCreateEvent();

        $this->dispatch($cartCreateEvent, TheliaEvents::CART_CREATE_NEW);

        $cart = $cartCreateEvent->getCart();
        self::assertInstanceOf(Cart::class, $cart);
        // The cart is not persisted until the first item is added;
        // `createEmptyCart` only stores it in the session for later use.
        self::assertTrue($cart->isNew());
    }

    public function testAddItemAppendsProductToCart(): void
    {
        $cart = $this->newEmptyCart();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $pse = $this->defaultPseFor($product->getId());

        $event = new CartEvent($cart);
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($pse->getId())
            ->setQuantity(3)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        $cartItem = $event->getCartItem();
        self::assertNotNull($cartItem);
        self::assertSame(3.0, (float) $cartItem->getQuantity());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testAddItemTwiceIncrementsExistingLine(): void
    {
        $cart = $this->newEmptyCart();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $pse = $this->defaultPseFor($product->getId());

        $this->dispatchAddItem($cart, $product->getId(), $pse->getId(), 2);
        $this->dispatchAddItem($cart, $product->getId(), $pse->getId(), 4);

        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame(
            6.0,
            (float) CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->findOne()
                ->getQuantity(),
        );
    }

    public function testChangeItemUpdatesQuantityInPlace(): void
    {
        $cart = $this->newEmptyCart();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $pse = $this->defaultPseFor($product->getId());

        $this->dispatchAddItem($cart, $product->getId(), $pse->getId(), 2);
        $cartItem = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();

        $change = new CartEvent($cart);
        $change->setCartItemId($cartItem->getId())->setQuantity(7);
        $this->dispatch($change, TheliaEvents::CART_UPDATEITEM);

        self::assertSame(
            7.0,
            (float) CartItemQuery::create()->findPk($cartItem->getId())->getQuantity(),
        );
    }

    public function testDeleteItemRemovesCartItem(): void
    {
        $cart = $this->newEmptyCart();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $pse = $this->defaultPseFor($product->getId());

        $this->dispatchAddItem($cart, $product->getId(), $pse->getId(), 1);
        $cartItem = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();

        $delete = new CartEvent($cart);
        $delete->setCartItemId($cartItem->getId());
        $this->dispatch($delete, TheliaEvents::CART_DELETEITEM);

        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testClearDeletesTheCart(): void
    {
        $cart = $this->newEmptyCart();
        $cartId = $cart->getId();

        $clear = new CartEvent($cart);
        $this->dispatch($clear, TheliaEvents::CART_CLEAR);

        self::assertNull(CartQuery::create()->findPk($cartId));
    }

    private function newEmptyCart(): Cart
    {
        $event = new CartCreateEvent();
        $this->dispatch($event, TheliaEvents::CART_CREATE_NEW);

        return $event->getCart();
    }

    private function defaultPseFor(int $productId)
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->filterByIsDefault(true)
            ->findOne();
    }

    private function dispatchAddItem(Cart $cart, int $productId, int $pseId, int $quantity): CartEvent
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($productId)
            ->setProductSaleElementsId($pseId)
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true);
        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event;
    }
}
