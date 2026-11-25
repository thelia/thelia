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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;

/**
 * The cart of a quote the buyer left, or never ordered, is not what the buyer finds in the session
 * when they sign in again: they find their own cart, and the quote stays accepted, to be paid when
 * they go back to it.
 */
final class QuoteCartLoginTest extends QuoteCartTestCase
{
    private Cart $quoteCart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quoteCart = $this->quoteCart();
        // A new visit: the session holds no cart, the cookie names the one the buyer left.
        $this->session()->remove(Session::SESSION_CART_ID_NAME);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), (string) $this->quoteCart->getToken());
    }

    public function testTheCartOfAQuoteNamedByTheCookieIsNotPutBackInTheSession(): void
    {
        $restored = $this->restore();

        self::assertNotSame($this->quoteCart->getId(), $restored->getId());
        self::assertNull($restored->getQuoteId(), 'an ordinary cart');
        self::assertSame($this->buyer->getId(), $restored->getCustomerId());
        self::assertNotSame($this->quoteCart->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
    }

    public function testTheCartTheBuyerHadSetAsideComesBack(): void
    {
        $ordinary = $this->ordinaryCart();
        $this->quoteCart->setSetAsideCartId($ordinary->getId())->save($this->getPropelConnection());

        $restored = $this->restore();

        self::assertSame($ordinary->getId(), $restored->getId());
        self::assertSame($ordinary->getId(), $this->session()->get(Session::SESSION_CART_ID_NAME));
        self::assertNull($this->reloaded($this->quoteCart)->getSetAsideCartId(), 'a cart is set aside once');
    }

    public function testTheCartSetAsideOfAnotherBuyerIsNeverHandedOver(): void
    {
        $stranger = $this->factory->customer($this->factory->customerTitle());
        $theirs = $this->ordinaryCart($stranger);
        $this->quoteCart->setSetAsideCartId($theirs->getId())->save($this->getPropelConnection());

        $restored = $this->restore();

        self::assertNotSame($theirs->getId(), $restored->getId());
        self::assertSame($this->buyer->getId(), $restored->getCustomerId());
    }

    public function testTheQuoteStaysAcceptableAndItsCartIsAsItWas(): void
    {
        $this->restore();

        $quoteCart = $this->reloaded($this->quoteCart);
        self::assertSame($this->quote->getId(), $quoteCart->getQuoteId());
        self::assertSame(90.0, round((float) $this->lineOf($quoteCart)->getPrice(), 6), 'the line was not priced again');
        self::assertSame('accepted', (string) \Thelia\Model\QuoteQuery::create()->findPk($this->quote->getId())?->getState());

        $order = $this->pay($quoteCart);

        self::assertSame($this->quote->getId(), $order->getQuoteId(), 'the buyer who goes back to it pays the quote');
    }

    public function testAnOrdinaryCartNamedByTheCookieIsRestoredAsEver(): void
    {
        $ordinary = $this->ordinaryCart();
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), (string) $ordinary->getToken());

        $restored = $this->restore();

        self::assertNull($restored->getQuoteId());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($restored->getId())->count(), 'the lines of the cart came along');
    }

    private function restore(): Cart
    {
        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        return $event->getCart() ?? throw new \LogicException('No cart was restored.');
    }
}
