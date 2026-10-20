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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Type;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * A free product coupon puts its gift in the cart as soon as one of the selected
 * products is there, and grants the price of one gift as its discount.
 */
final class FreeProductTest extends ActionIntegrationPromotionTestCase
{
    public function testASelectedProductInTheCartBringsOneGiftWhosePriceIsTheDiscount(): void
    {
        $trigger = $this->product(['basePrice' => 10.0]);
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        $discount = $this->freeProduct('GIFT', [$trigger->getId()], $gift)->exec();

        $giftLine = $this->singleLineOf($cart, $gift);
        self::assertSame(1.0, (float) $giftLine->getQuantity(), 'One gift, whatever the quantity bought.');
        self::assertSame($this->taxedUnitPrice($giftLine), $discount);
        self::assertSame(8.4, $discount, 'The gift price, 7.0, taxed at the 20 % of the tax rule the products use.');
    }

    public function testTheGiftIsStillDiscountedAndNotAddedAgainOnTheNextEvaluation(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);
        $coupon = $this->freeProduct('GIFT', [$trigger->getId()], $gift);

        $firstDiscount = $coupon->exec();
        $secondDiscount = $coupon->exec();

        self::assertSame($firstDiscount, $secondDiscount);
        self::assertSame(1.0, (float) $this->singleLineOf($cart, $gift)->getQuantity());
    }

    public function testAGiftTheCustomerAlreadyPutInTheCartIsDiscountedInsteadOfAddedAgain(): void
    {
        $trigger = $this->product(['basePrice' => 10.0]);
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);
        $giftLine = $this->addItem($cart, $gift);

        $discount = $this->freeProduct('GIFT', [$trigger->getId()], $gift)->exec();

        self::assertCount(2, $this->linesOf($cart), 'No line is added for a gift already in the cart.');
        self::assertSame($giftLine->getId(), $this->singleLineOf($cart, $gift)->getId());
        self::assertSame($this->taxedUnitPrice($giftLine), $discount);
    }

    public function testAProductOutsideTheSelectionBringsNoGift(): void
    {
        $gift = $this->product();
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $discount = $this->freeProduct('GIFT', [$this->product()->getId()], $gift)->exec();

        self::assertSame(0.0, $discount);
        self::assertCount(1, $this->linesOf($cart));
    }

    public function testASelectedProductOnSpecialOfferBringsNoGiftUnlessTheCouponAllowsIt(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger)->setPromo(1)->save();

        $discount = $this->freeProduct('GIFT', [$trigger->getId()], $gift)->exec();

        self::assertSame(0.0, $discount);
        self::assertCount(1, $this->linesOf($cart));
    }

    public function testASelectedProductOnSpecialOfferBringsTheGiftWhenTheCouponAllowsIt(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger)->setPromo(1)->save();

        $discount = $this->freeProduct('GIFT', [$trigger->getId()], $gift, availableOnSpecialOffers: true)->exec();

        self::assertSame($this->taxedUnitPrice($this->singleLineOf($cart, $gift)), $discount);
    }

    /**
     * Each coupon keeps track of its own gift: a second coupon on the same
     * product must not take the first one's gift for its own.
     */
    public function testTwoCouponsOnTheSameProductEachBringTheirOwnGift(): void
    {
        $trigger = $this->product();
        $firstGift = $this->product(['basePrice' => 7.0]);
        $secondGift = $this->product(['basePrice' => 4.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        $this->freeProduct('FIRST-GIFT', [$trigger->getId()], $firstGift)->exec();
        $discount = $this->freeProduct('SECOND-GIFT', [$trigger->getId()], $secondGift)->exec();

        self::assertSame($this->taxedUnitPrice($this->singleLineOf($cart, $secondGift)), $discount);
        self::assertSame(1.0, (float) $this->singleLineOf($cart, $firstGift)->getQuantity(), 'The first coupon keeps its gift.');
    }

    /**
     * A gift whose addition was refused once (a module listening to the cart
     * addition said no) is tried again once the trigger left the cart and came
     * back: the coupon forgets what it was doing when no product triggers it.
     */
    public function testTheGiftComesBackWithItsPriceWhenTheTriggerLeavesAndReturns(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $triggerLine = $this->addItem($cart, $trigger);
        $coupon = $this->freeProduct('GIFT', [$trigger->getId()], $gift);
        $this->refusingTheNextCartAddition(static fn () => $coupon->exec());

        $this->deleteItem($cart, $triggerLine);
        self::assertSame(0.0, $coupon->exec(), 'Nothing triggers the gift.');
        $this->addItem($cart, $trigger);
        $discount = $coupon->exec();

        self::assertSame($this->taxedUnitPrice($this->singleLineOf($cart, $gift)), $discount);
        self::assertSame(8.4, $discount);
    }

    /**
     * The same reset when the conditions of the coupon are no longer met.
     */
    public function testTheGiftIsTriedAgainOnceTheConditionsWereFoundUnmet(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);
        $coupon = $this->freeProduct('GIFT', [$trigger->getId()], $gift);
        $coupon->setConditions($this->getService(ConditionFactory::class)->unserializeConditionCollection($this->atLeastLines(2)));
        $this->refusingTheNextCartAddition(static fn () => $coupon->exec());

        self::assertFalse($coupon->isMatching(), 'One line, two required.');
        $discount = $coupon->exec();

        self::assertSame($this->taxedUnitPrice($this->singleLineOf($cart, $gift)), $discount);
        self::assertSame(8.4, $discount);
    }

    /**
     * A module that forbids buying a product listens to the cart addition before
     * the cart does: it must be told which product the gift is.
     */
    public function testAListenerRunningBeforeTheCartIsToldWhichProductIsOffered(): void
    {
        $trigger = $this->product();
        $gift = $this->product();
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        $productIdsSeen = [];
        $listener = static function (CartEvent $event) use (&$productIdsSeen): void {
            $productIdsSeen[] = $event->getProductId();
        };
        $this->dispatcher->addListener(TheliaEvents::CART_ADDITEM, $listener, 200);

        try {
            $this->freeProduct('GIFT', [$trigger->getId()], $gift)->exec();
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::CART_ADDITEM, $listener);
        }

        self::assertSame([$gift->getId()], $productIdsSeen);
    }

    /**
     * The whole machinery: the gift is added from inside a discount evaluation,
     * which the cart addition triggers again. It must land once, and be the
     * discount of the cart.
     */
    public function testAnAutomaticFreeProductPromotionPutsItsGiftInTheCartOnce(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0]);
        $this->automaticPromotion(
            type: 'thelia.coupon.type.free_product',
            effects: [
                FreeProduct::PRODUCTS_LIST => [$trigger->getId()],
                FreeProduct::CATEGORY_ID => 0,
                FreeProduct::OFFERED_PRODUCT_ID => $gift->getId(),
                FreeProduct::OFFERED_CATEGORY_ID => 0,
            ],
            conditions: $this->atLeastLines(1),
        );
        $cart = $this->newEmptyCart();

        $this->addItem($cart, $trigger);

        $giftLine = $this->singleLineOf($cart, $gift);
        self::assertSame(1.0, (float) $giftLine->getQuantity());
        $cart->reload();
        self::assertSame(round($this->taxedUnitPrice($giftLine), 2), (float) $cart->getDiscount());
    }

    /**
     * A gift out of stock is not offered: the product that triggers it still goes
     * to the cart, and the customer is not refused for a gift they did not ask for.
     */
    public function testAGiftOutOfStockIsNotAddedAndTheTriggerStillIs(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 7.0, 'baseQuantity' => 0]);
        $this->automaticPromotion(
            type: 'thelia.coupon.type.free_product',
            effects: [
                FreeProduct::PRODUCTS_LIST => [$trigger->getId()],
                FreeProduct::CATEGORY_ID => 0,
                FreeProduct::OFFERED_PRODUCT_ID => $gift->getId(),
                FreeProduct::OFFERED_CATEGORY_ID => 0,
            ],
            conditions: $this->atLeastLines(1),
        );
        $cart = $this->newEmptyCart();

        $this->addItem($cart, $trigger);

        self::assertSame(1.0, (float) $this->singleLineOf($cart, $trigger)->getQuantity());
        self::assertSame([], array_values(array_filter(
            $this->linesOf($cart),
            static fn (CartItem $line): bool => $line->getProductId() === $gift->getId(),
        )), 'The gift out of stock is not in the cart.');
        $cart->reload();
        self::assertSame(0.0, (float) $cart->getDiscount());
    }

    private function refusingTheNextCartAddition(callable $action): void
    {
        $listener = static function (): void {
            throw new InvalidCartException('Refused by a module');
        };
        $this->dispatcher->addListener(TheliaEvents::CART_ADDITEM, $listener, 200);

        try {
            $action();
            self::fail('The cart addition was expected to be refused.');
        } catch (InvalidCartException) {
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::CART_ADDITEM, $listener);
        }
    }

    /**
     * @param int[] $productIds
     */
    private function freeProduct(
        string $code,
        array $productIds,
        ?Product $gift,
        bool $availableOnSpecialOffers = false,
    ): FreeProduct {
        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');

        $coupon = new FreeProduct($facade);
        $coupon->set(
            $facade,
            $code,
            'Free gift',
            '',
            '',
            [
                FreeProduct::PRODUCTS_LIST => $productIds,
                FreeProduct::CATEGORY_ID => 0,
                FreeProduct::OFFERED_PRODUCT_ID => $gift?->getId(),
                FreeProduct::OFFERED_CATEGORY_ID => 0,
            ],
            true,
            false,
            $availableOnSpecialOffers,
            true,
            -1,
            new \DateTime('+1 month'),
            [],
            [],
            false,
        );

        return $coupon;
    }

    /**
     * @return CartItem[]
     */
    private function linesOf(Cart $cart): array
    {
        CartItemTableMap::clearInstancePool();

        return iterator_to_array(CartItemQuery::create()->filterByCartId($cart->getId())->find(), false);
    }

    private function singleLineOf(Cart $cart, Product $product): CartItem
    {
        $lines = array_values(array_filter(
            $this->linesOf($cart),
            static fn (CartItem $line): bool => $line->getProductId() === $product->getId(),
        ));

        self::assertCount(1, $lines);

        return $lines[0];
    }

    private function taxedUnitPrice(CartItem $line): float
    {
        /** @var FacadeInterface $facade */
        $facade = $this->getService('thelia.facade');

        return $line->getRealTaxedPrice($facade->getDeliveryCountry());
    }

    private function atLeastLines(int $lines): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => $lines],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
