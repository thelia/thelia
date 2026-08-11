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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
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

    public function testCreateEmptyCartAsksForTheCartCookieRemoval(): void
    {
        self::assertSame(1, (int) ConfigQuery::read('cart.use_persistent_cookie', 1));

        $this->dispatch(new CartCreateEvent(), TheliaEvents::CART_CREATE_NEW);

        // An empty string is the signal ResponseListener reads to clear the cart cookie.
        self::assertSame('', $this->mainRequest()->getSession()->get('cart_use_cookie'));
    }

    public function testRestoreCurrentCartDuplicatesACartOwnedByAnotherCustomer(): void
    {
        $owner = $this->factory->customer($this->factory->customerTitle());
        $cart = $this->factory->cart($owner);
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $this->dispatchAddItem($cart, $product->getId(), $this->defaultPseFor($product->getId())->getId(), 1);

        $this->mainRequest()->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $cart->getToken(),
        );

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        $restored = $event->getCart();
        self::assertInstanceOf(Cart::class, $restored);
        self::assertNotSame($cart->getId(), $restored->getId());
        self::assertNull($restored->getCustomerId());
        // The duplicate carries a fresh token, which is also what ends up in the cookie.
        self::assertNotNull($restored->getToken());
        self::assertNotSame($cart->getToken(), $restored->getToken());
    }

    public function testRestoreCurrentCartRealignsItemPricesWithTheCatalog(): void
    {
        $cart = $this->factory->cart();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 100],
        );
        $pse = $this->defaultPseFor($product->getId());
        $this->dispatchAddItem($cart, $product->getId(), $pse->getId(), 1);

        // The item was added while the product was on a special offer, which has since ended.
        $item = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        $item->setPromo(1)->setPromoPrice('4.44')->save();
        self::assertSame(4.44, $item->getRealPrice());

        $this->mainRequest()->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $cart->getToken(),
        );

        $this->dispatch(new CartRestoreEvent(), TheliaEvents::CART_RESTORE_CURRENT);

        $catalogPrice = $pse->getPricesByCurrency($cart->getCurrency(), 0);
        $item->reload();

        self::assertSame(0, (int) $item->getPromo());
        self::assertSame((float) $catalogPrice->getPromoPrice(), (float) $item->getPromoPrice());
        self::assertSame((float) $catalogPrice->getPrice(), $item->getRealPrice());
    }

    public function testDuplicateAcceptsANullTokenWhenTheCartCookieIsDisabled(): void
    {
        $cart = $this->newEmptyCart();
        $cart->save();

        $duplicate = $cart->duplicate(
            null,
            null,
            $this->factory->currency(),
            $this->getService(EventDispatcherInterface::class),
        );

        self::assertInstanceOf(Cart::class, $duplicate);
        self::assertNull($duplicate->getToken());
    }

    private function mainRequest(): Request
    {
        return $this->getService(RequestStack::class)->getMainRequest();
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
