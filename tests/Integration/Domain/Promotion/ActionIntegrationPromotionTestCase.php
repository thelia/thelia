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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Category;
use Thelia\Model\Coupon;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Shared ground for the promotion tests: a catalog to build carts from, the
 * cart events that drive the coupon machinery, and a builder for automatic
 * promotions.
 */
abstract class ActionIntegrationPromotionTestCase extends ActionIntegrationTestCase
{
    private Category $category;
    private TaxRule $taxRule;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = $this->factory->category();
        $this->taxRule = $this->factory->taxRule();
        $this->currency = $this->factory->currency();

        // Each test starts on an empty session: the coupons of the previous one
        // must not leak into this one's evaluation, and neither must the
        // unavailable-promotions state the reconciliations persist there.
        $this->session()->setConsumedCoupons([]);
        $this->session()->remove(OfferedCartLineService::UNAVAILABLE_PROMOTIONS_SESSION_KEY);
        $this->getService(CouponManager::class)->invalidateCurrentCoupons();
        $this->getService(OfferedCartLineService::class)->reset();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function product(array $overrides = []): Product
    {
        return $this->productIn($this->category, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function productIn(Category $category, array $overrides = []): Product
    {
        return $this->factory->product(
            $category,
            $this->taxRule,
            $this->currency,
            array_merge(['baseQuantity' => 100, 'basePrice' => 10.0], $overrides),
        );
    }

    protected function defaultPseFor(Product $product): ProductSaleElements
    {
        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        self::assertNotNull($productSaleElements);

        return $productSaleElements;
    }

    protected function newEmptyCart(): Cart
    {
        $event = new CartCreateEvent();
        $this->dispatch($event, TheliaEvents::CART_CREATE_NEW);

        $cart = $event->getCart();
        self::assertInstanceOf(Cart::class, $cart);

        return $cart;
    }

    protected function addItem(Cart $cart, Product $product, int $quantity = 1, bool $append = false): CartItem
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($this->defaultPseFor($product)->getId())
            ->setQuantity($quantity)
            ->setNewness(!$append)
            ->setAppend($append);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        $cartItem = $event->getCartItem();
        self::assertInstanceOf(CartItem::class, $cartItem);

        return $cartItem;
    }

    protected function changeItemQuantity(Cart $cart, CartItem $cartItem, float $quantity): CartEvent
    {
        $event = new CartEvent($cart);
        $event
            ->setCartItemId($cartItem->getId())
            ->setQuantity($quantity);

        $this->dispatch($event, TheliaEvents::CART_UPDATEITEM);

        return $event;
    }

    protected function deleteItem(Cart $cart, CartItem $cartItem): void
    {
        $event = new CartEvent($cart);
        $event->setCartItemId($cartItem->getId());

        $this->dispatch($event, TheliaEvents::CART_DELETEITEM);
    }

    /**
     * Replays what every cart mutation ends with: rebuild the coupons, put the
     * offered lines back in line, write the discount.
     */
    protected function recomputeDiscount(): void
    {
        $this->getService(CouponAction::class)->updateOrderDiscount(
            new Event(),
            'test.recompute',
            $this->dispatcher,
        );
    }

    /**
     * @param array<string, mixed> $effects
     */
    protected function automaticPromotion(
        string $type = 'thelia.coupon.type.remove_x_amount',
        array $effects = ['amount' => 5.0],
        string $conditions = '',
        string $title = 'Automatic promotion',
        array $overrides = [],
    ): Coupon {
        return $this->factory->coupon(array_merge([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'type' => $type,
            'effects' => $effects,
            'conditions' => $conditions,
            'title' => $title,
            'isCumulative' => true,
        ], $overrides));
    }

    protected function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
