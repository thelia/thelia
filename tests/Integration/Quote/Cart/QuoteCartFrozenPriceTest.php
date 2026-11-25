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

use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Cart\Service\CartDiscountReconciler;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Cart\Service\CartPriceGuard;
use Thelia\Domain\Cart\Service\CartProductVisibilitySubscriber;
use Thelia\Domain\Cart\Service\CartQuantityReconciler;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;

/**
 * The line of a quote holds the price the shop and the buyer agreed on, 90, where the group of
 * the buyer would give 85: nothing prices it again, by whatever door the cart is read.
 *
 * Every test sets an ordinary cart at the same prices beside it, and sees it move: the shop does
 * price the buyer apart, and what holds the line of the quote still is the quote.
 */
final class QuoteCartFrozenPriceTest extends QuoteCartTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->priceTheBuyerApart();
    }

    public function testThePricerLeavesTheLineOfAQuoteAndMovesTheOtherOne(): void
    {
        $quoteCart = $this->quoteCart();
        $ordinaryCart = $this->ordinaryCart();
        $pricer = $this->getService(CartLinePricer::class);
        $currency = Currency::getDefaultCurrency();

        self::assertSame([], $pricer->staleLinesOf($quoteCart, $currency, $this->buyer));
        self::assertCount(1, $pricer->staleLinesOf($ordinaryCart, $currency, $this->buyer), 'the shop prices this buyer apart: the same line without a quote is stale');

        self::assertSame([], $pricer->reprice($quoteCart, $currency, $this->buyer));
        self::assertSame(90.0, round((float) $this->lineOf($quoteCart)->getPrice(), 6));

        self::assertCount(1, $pricer->reprice($ordinaryCart, $currency, $this->buyer));
        self::assertSame(85.0, round((float) $this->lineOf($ordinaryCart)->getPrice(), 6));
    }

    public function testThePricerNeverWritesALineOfAQuoteBeingAdded(): void
    {
        $quoteCart = $this->quoteCart();
        $line = $this->lineOf($quoteCart);

        self::assertSame([], $this->getService(CartLinePricer::class)->price([$line], Currency::getDefaultCurrency(), $this->buyer));
        self::assertSame('90.000000', $line->getPrice());
    }

    public function testThePriceGuardRefusesTheOrdinaryCartAndNotTheCartOfAQuote(): void
    {
        $guard = $this->getService(CartPriceGuard::class);
        $quoteCart = $this->quoteCart();
        $ordinaryCart = $this->ordinaryCart();
        $currency = Currency::getDefaultCurrency();

        $guard->refuseIfStale($quoteCart, $currency, $this->buyer);
        $guard->settleOrRefuse($quoteCart, $currency, $this->buyer);
        self::assertSame(90.0, round((float) $this->lineOf($quoteCart)->getPrice(), 6));

        $this->expectException(CartPricesChangedException::class);
        $guard->refuseIfStale($ordinaryCart, $currency, $this->buyer);
    }

    public function testAChangeOfCurrencyLeavesTheCartOfAQuoteInTheCurrencyItWasWrittenIn(): void
    {
        $quoteCart = $this->quoteCart();
        $ordinaryCart = $this->ordinaryCart();
        $other = $this->factory->currency(['code' => 'XQC', 'symbol' => 'XQ', 'rate' => 2.0]);
        $action = $this->getService(CartAction::class);

        $action->updateCartPrices($quoteCart, $other);

        $quoteCart = $this->reloaded($quoteCart);
        self::assertNotSame($other->getId(), $quoteCart->getCurrencyId());
        self::assertSame(90.0, round((float) $this->lineOf($quoteCart)->getPrice(), 6));

        $action->updateCartPrices($ordinaryCart, $other);

        self::assertSame($other->getId(), $this->reloaded($ordinaryCart)->getCurrencyId());
    }

    public function testACartRestoredIsNeitherCopiedNorPricedAgainNorHandedBackWhenItIsTheCartOfAQuote(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '0');
        ConfigQuery::resetCache();
        $quoteCart = $this->quoteCart();

        $event = new CartRestoreEvent();
        $event->setCart($quoteCart);
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        self::assertNotSame($quoteCart->getId(), $event->getCart()?->getId(), 'the buyer signs in on their own cart, not on the cart of a quote they left');
        self::assertNull($event->getCart()?->getQuoteId());
        $quoteCart = $this->reloaded($quoteCart);
        self::assertSame($this->quote->getId(), $quoteCart->getQuoteId(), 'the cart of the quote is neither copied nor emptied: it is there for the buyer who pays the quote');
        self::assertSame(90.0, round((float) $this->lineOf($quoteCart)->getPrice(), 6));
    }

    public function testACartOfAQuoteIsNotHandedToAnotherBuyerWhenItIsRestored(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '0');
        ConfigQuery::resetCache();
        $quoteCart = $this->quoteCart();
        $stranger = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($stranger);

        $event = new CartRestoreEvent();
        $event->setCart($quoteCart);
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        self::assertNotSame($quoteCart->getId(), $event->getCart()?->getId());
        self::assertNull($event->getCart()?->getQuoteId());
    }

    public function testASignInDoesNotPriceTheLineOfAQuoteAgainNorTakeItOut(): void
    {
        $quoteCart = $this->quoteCart();

        $this->getService(CartProductVisibilitySubscriber::class)->settleTheCartOfTheCustomer(new CustomerLoginEvent($this->buyer));

        $line = $this->lineOf($quoteCart);
        self::assertSame(90.0, round((float) $line->getPrice(), 6));
        self::assertSame($this->quoteLine->getId(), $line->getId());
    }

    public function testASignInPricesAnOrdinaryCartAgain(): void
    {
        $ordinaryCart = $this->ordinaryCart();
        $this->session()->set(Session::SESSION_CART_ID_NAME, $ordinaryCart->getId());

        $this->getService(CartProductVisibilitySubscriber::class)->settleTheCartOfTheCustomer(new CustomerLoginEvent($this->buyer));

        self::assertSame(85.0, round((float) $this->lineOf($ordinaryCart)->getPrice(), 6));
    }

    public function testTheReconciliationOfTheDiscountDoesNotRewriteTheDiscountOfAQuote(): void
    {
        $this->tenPercentOffPromotion();
        $quoteCart = $this->quoteCart();
        $reconciler = $this->getService(CartDiscountReconciler::class);

        // The cart no session holds, as the front API reads it.
        $this->session()->remove(Session::SESSION_CART_ID_NAME);
        $reconciler->reconcile($quoteCart, $this->buyer, $reconciler->discountIsThePromotionsOwn($quoteCart, $this->buyer));
        self::assertSame(30.0, (float) $this->reloaded($quoteCart)->getDiscount(), 'no session');

        // The cart the session holds: the promotions hear the repricing of the lines.
        $this->session()->set(Session::SESSION_CART_ID_NAME, $quoteCart->getId());
        $reconciler->reconcile($quoteCart, $this->buyer, true);
        self::assertSame(30.0, (float) $this->reloaded($quoteCart)->getDiscount(), 'session');
    }

    public function testTheReconciliationOfTheDiscountPricesTheOrdinaryCartAgain(): void
    {
        $this->tenPercentOffPromotion();
        $ordinaryCart = $this->ordinaryCart();
        $reconciler = $this->getService(CartDiscountReconciler::class);

        $reconciler->reconcile($ordinaryCart, $this->buyer, true);

        self::assertGreaterThan(0.0, (float) $this->reloaded($ordinaryCart)->getDiscount(), 'the promotion applies to a cart that is no quote');
    }

    public function testTheQuantityRulesDoNotBringTheLineOfAQuoteDown(): void
    {
        $quoteCart = $this->quoteCart();
        $line = $this->lineOf($quoteCart);
        // 12.5 is off the step of a product sold by the unit: an ordinary line would be brought to 12.
        $line->setQuantity(12.5)->save($this->getPropelConnection());

        $adjusted = (new CartQuantityReconciler())->reconcile($this->reloaded($quoteCart), $this->dispatcher);

        self::assertSame([], $adjusted);
        self::assertSame(12.5, (float) $this->lineOf($quoteCart)->getQuantity());

        $ordinaryCart = $this->ordinaryCart();
        $this->lineOf($ordinaryCart)->setQuantity(12.5)->save($this->getPropelConnection());

        self::assertNotSame([], (new CartQuantityReconciler())->reconcile($this->reloaded($ordinaryCart), $this->dispatcher), 'the same quantity on an ordinary line is brought to the rules');
    }
}
