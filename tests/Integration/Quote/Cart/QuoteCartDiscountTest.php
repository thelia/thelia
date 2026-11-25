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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Quote\Cart\QuoteCartLockedException;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Coupon;

/**
 * The discount of the cart of a quote is the one the revision carries: no promotion prices it again,
 * no gift is put on its lines, and no code can be typed in.
 */
final class QuoteCartDiscountTest extends QuoteCartTestCase
{
    public function testAPromotionThatAppliesOnItsOwnDoesNotRewriteTheDiscountOfAQuote(): void
    {
        $this->tenPercentOffPromotion();
        $quoteCart = $this->quoteCart();

        $this->recomputeDiscount();

        self::assertSame(30.0, (float) $this->reloaded($quoteCart)->getDiscount());
    }

    public function testThePromotionStillAppliesToAnOrdinaryCartOfTheSession(): void
    {
        $this->tenPercentOffPromotion();
        $ordinaryCart = $this->ordinaryCart();
        $this->session()->set(Session::SESSION_CART_ID_NAME, $ordinaryCart->getId());

        $this->recomputeDiscount();

        self::assertSame(108.0, (float) $this->reloaded($ordinaryCart)->getDiscount(), '10 % of 12 x 90');
    }

    public function testTheEventsOfTheCheckoutDoNotRewriteTheDiscountOfAQuoteEither(): void
    {
        $this->tenPercentOffPromotion();
        $quoteCart = $this->quoteCart();
        $linesBefore = CartItemQuery::create()->filterByCartId($quoteCart->getId())->count();

        foreach ([TheliaEvents::CART_LINES_REPRICED, TheliaEvents::CART_LINES_REMOVED, TheliaEvents::CUSTOMER_LOGIN] as $eventName) {
            if (TheliaEvents::CUSTOMER_LOGIN === $eventName) {
                $this->dispatch(new CustomerLoginEvent($this->buyer), $eventName);

                continue;
            }

            $this->dispatch(new CartEvent($quoteCart), $eventName);
        }

        self::assertSame(30.0, (float) $this->reloaded($quoteCart)->getDiscount());
        self::assertSame($linesBefore, CartItemQuery::create()->filterByCartId($quoteCart->getId())->count());
    }

    public function testACodeCannotBeUsedOnTheCartOfAQuote(): void
    {
        $this->factory->coupon(['code' => 'QUOTE10', 'type' => 'thelia.coupon.type.remove_x_percent', 'effects' => ['percentage' => 10.0], 'conditions' => $this->atLeastOneLine()]);
        $quoteCart = $this->quoteCart();

        try {
            $this->dispatch(new CouponConsumeEvent('QUOTE10'), TheliaEvents::COUPON_CONSUME);
            self::fail('A code was applied to the cart of a quote.');
        } catch (QuoteCartLockedException $refusal) {
            self::assertSame(QuoteCartLockedException::COUPON_REFUSED, $refusal->reason());
            self::assertSame('A promo code cannot be used on a quote.', $refusal->getMessage());
        }

        self::assertSame([], $this->session()->getConsumedCoupons() ?? []);
        self::assertSame(30.0, (float) $this->reloaded($quoteCart)->getDiscount());
    }

    public function testACodeIsStillUsedOnAnOrdinaryCart(): void
    {
        $this->factory->coupon(['code' => 'ORDINARY10', 'type' => 'thelia.coupon.type.remove_x_percent', 'effects' => ['percentage' => 10.0], 'conditions' => $this->atLeastOneLine(), 'triggerMode' => Coupon::TRIGGER_MODE_CODE]);
        $ordinaryCart = $this->ordinaryCart();
        $this->session()->set(Session::SESSION_CART_ID_NAME, $ordinaryCart->getId());

        $event = $this->dispatch(new CouponConsumeEvent('ORDINARY10'), TheliaEvents::COUPON_CONSUME);

        self::assertTrue($event->getIsValid());
        self::assertSame(108.0, (float) $this->reloaded($ordinaryCart)->getDiscount());
    }
}
