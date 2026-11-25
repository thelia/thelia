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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Quote\Cart\QuoteCartSwitcher;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderStatus;

/**
 * The cart of a quote takes the place of the cart of the buyer in the session while the quote is
 * paid, and gives it back once the quote is ordered or given up.
 */
final class QuoteCartSwitcherTest extends QuoteCartTestCase
{
    private Cart $ordinaryCart;

    private Cart $quoteCart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quoteCart = $this->quoteCart();
        $this->ordinaryCart = $this->ordinaryCart();
        $this->holdInSession($this->ordinaryCart);
    }

    public function testTheCartOfTheQuoteTakesThePlaceOfTheCartOfTheBuyerWhichIsSetAside(): void
    {
        self::assertTrue($this->switcher()->putInSession($this->quoteCart));

        self::assertSame($this->quoteCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
        self::assertSame($this->ordinaryCart->getId(), $this->reloaded($this->quoteCart)->getSetAsideCartId());
    }

    public function testThePlaceIsTakenOnceAndTheCartSetAsideIsKept(): void
    {
        $this->switcher()->putInSession($this->quoteCart);
        $this->switcher()->putInSession($this->reloaded($this->quoteCart));

        self::assertSame($this->ordinaryCart->getId(), $this->reloaded($this->quoteCart)->getSetAsideCartId(), 'the cart of the quote is never set aside for itself');
    }

    public function testTheCartOfAnotherQuoteIsNeverTheOneSetAside(): void
    {
        $other = $this->quoteCart();
        $this->holdInSession($other);

        $this->switcher()->putInSession($this->quoteCart);

        self::assertNull($this->reloaded($this->quoteCart)->getSetAsideCartId());
    }

    public function testAnOrdinaryCartIsNotPutInTheSessionAsIfItWasAQuote(): void
    {
        self::assertFalse($this->switcher()->putInSession($this->ordinaryCart));
    }

    public function testTheCartSetAsideComesBackAndIsNotSetAsideAnyMore(): void
    {
        $this->switcher()->putInSession($this->quoteCart);

        $back = $this->switcher()->restoreSetAside();

        self::assertSame($this->ordinaryCart->getId(), $back?->getId());
        self::assertSame($this->ordinaryCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
        self::assertNull($this->reloaded($this->quoteCart)->getSetAsideCartId());
        self::assertNull($this->switcher()->restoreSetAside(), 'nothing is left to give back');
    }

    public function testNothingComesBackWhenNothingWasSetAside(): void
    {
        $this->holdInSession($this->quoteCart);

        self::assertNull($this->switcher()->restoreSetAside());
        self::assertSame($this->quoteCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
    }

    public function testTheCartSetAsideIsNotHandedToAnotherBuyer(): void
    {
        $this->switcher()->putInSession($this->quoteCart);
        $this->getService(SecurityContext::class)->setCustomerUser($this->factory->customer($this->factory->customerTitle()));

        self::assertNull($this->switcher()->restoreSetAside());
        self::assertSame($this->quoteCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
    }

    public function testTheCartSetAsideDoesNotComeBackOnceItWasOrdered(): void
    {
        $this->switcher()->putInSession($this->quoteCart);
        $this->factory->order($this->buyer)->setCartId($this->ordinaryCart->getId())->save($this->getPropelConnection());

        self::assertNull($this->switcher()->restoreSetAside());
    }

    public function testARequestWithoutASessionSwitchesNothingAndFailsNowhere(): void
    {
        self::assertFalse($this->switcher()->putInSession($this->quoteCart, new Request()));
        self::assertNull($this->switcher()->restoreSetAside(new Request()));
        self::assertNull($this->reloaded($this->quoteCart)->getSetAsideCartId());
    }

    public function testTheBuyerWhoseQuoteWasPaidFindsTheirCartAgainInsteadOfAnEmptyOne(): void
    {
        $this->switcher()->putInSession($this->quoteCart);
        $order = $this->factory->order($this->buyer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setCartId($this->quoteCart->getId())->save($this->getPropelConnection());

        // What the session does at the next page: the cart of a paid order is consumed.
        $cart = $this->session()->getSessionCart($this->dispatcher);

        self::assertSame($this->ordinaryCart->getId(), $cart->getId());
        self::assertSame($this->ordinaryCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
    }

    public function testTheBuyerWhoLeavesTheCartOfTheQuoteIsGivenTheirCartAgain(): void
    {
        $this->switcher()->putInSession($this->quoteCart);

        $this->session()->clearSessionCart($this->dispatcher);

        self::assertSame($this->ordinaryCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
    }

    public function testTheBuyerWhoGivesTheQuoteUpGetsTheirCartBackAndTheCartOfTheQuoteIsDeleted(): void
    {
        $this->switcher()->putInSession($this->quoteCart);

        $this->dispatch(new CartEvent($this->reloaded($this->quoteCart)), TheliaEvents::CART_CLEAR);

        self::assertSame($this->ordinaryCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
        self::assertNull(CartQuery::create()->findPk($this->quoteCart->getId()));
        self::assertNotNull(CartQuery::create()->findPk($this->ordinaryCart->getId()));
    }

    public function testTheCartOfAQuoteTheSessionDoesNotHoldIsDeletedWithoutMovingTheSession(): void
    {
        $this->switcher()->putInSession($this->quoteCart);
        $elsewhere = $this->ordinaryCart();
        $this->holdInSession($elsewhere);

        $this->dispatch(new CartEvent($this->reloaded($this->quoteCart)), TheliaEvents::CART_CLEAR);

        self::assertSame($elsewhere->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
        self::assertNull(CartQuery::create()->findPk($this->quoteCart->getId()));
    }

    public function testAnEmptiedOrdinaryCartIsEmptiedAsEver(): void
    {
        $this->dispatch(new CartEvent($this->reloaded($this->ordinaryCart)), TheliaEvents::CART_CLEAR);

        self::assertNull(CartQuery::create()->findPk($this->ordinaryCart->getId()));
        self::assertNotNull(CartQuery::create()->findPk($this->quoteCart->getId()), 'the cart of a quote is none of its business');
    }

    private function switcher(): QuoteCartSwitcher
    {
        return $this->getService(QuoteCartSwitcher::class);
    }

    private function holdInSession(Cart $cart): void
    {
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
    }
}
