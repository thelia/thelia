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

namespace Thelia\Domain\Quote\Cart;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderQuery;

/**
 * Puts the cart of a quote where the buyer's cart is, and gives the buyer's cart back.
 *
 * The session holds one cart. The cart of a quote takes its place while the quote is paid, and the
 * cart it replaced is kept aside, on the cart of the quote, so that nothing the buyer put in it is
 * lost: it comes back once the quote is ordered or given up.
 *
 * A request with no session (a command, the stateless API) has no cart to switch: nothing is done,
 * and nothing fails.
 */
final readonly class QuoteCartSwitcher
{
    public function __construct(
        private RequestStack $requestStack,
        private QuoteCartInspector $inspector,
    ) {
    }

    /**
     * The cart of the quote becomes the cart of the session. The cart it replaces is remembered on it,
     * unless that one is a cart of a quote itself, or the cart of the quote: the cart set aside is
     * always an ordinary cart.
     *
     * @return bool whether the session holds the cart of the quote now
     */
    public function putInSession(Cart $quoteCart, Request|Session|null $from = null): bool
    {
        $session = $this->sessionOf($from);

        if (!$session instanceof Session || !$this->inspector->isQuoteCart($quoteCart) || null === $quoteCart->getId()) {
            return false;
        }

        $current = $this->sessionCartOf($session);

        if (null !== $current && !$this->inspector->isQuoteCart($current) && $current->getId() !== $quoteCart->getId()) {
            $quoteCart->setSetAsideCartId($current->getId())->save();
        }

        $session->setSessionCart($quoteCart);
        $this->followTheCurrencyOf($session, $quoteCart);

        return true;
    }

    /**
     * The cart that was set aside comes back into the session, in place of the cart of the quote (the
     * one the session holds, unless it is given), which is left as it is. Nothing comes back, and
     * null is answered, when nothing was set aside, when the buyer who holds the session is not the
     * one the cart belongs to, or when it has been ordered since.
     */
    public function restoreSetAside(Request|Session|null $from = null, ?Cart $quoteCart = null): ?Cart
    {
        $session = $this->sessionOf($from);

        if (!$session instanceof Session) {
            return null;
        }

        $quoteCart ??= $this->sessionCartOf($session);

        if (null === $quoteCart || !$this->inspector->isQuoteCart($quoteCart) || null === $quoteCart->getSetAsideCartId()) {
            return null;
        }

        $setAside = CartQuery::create()->findPk($quoteCart->getSetAsideCartId());

        // Whatever the answer, a cart is set aside once.
        $quoteCart->setSetAsideCartId(null);

        if (!$quoteCart->isNew()) {
            $quoteCart->save();
        }

        if (null === $setAside || !$this->belongsToTheBuyerOf($session, $setAside) || OrderQuery::create()->filterByCartId($setAside->getId())->exists()) {
            return null;
        }

        $session->setSessionCart($setAside);
        $this->followTheCurrencyOf($session, $setAside);

        return $setAside;
    }

    private function sessionOf(Request|Session|null $from): ?Session
    {
        if ($from instanceof Session) {
            return $from;
        }

        $request = $from ?? $this->requestStack->getMainRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof Session ? $session : null;
    }

    private function sessionCartOf(Session $session): ?Cart
    {
        $cartId = $session->get(Session::SESSION_CART_ID_NAME);

        return null === $cartId ? null : CartQuery::create()->findPk($cartId);
    }

    private function belongsToTheBuyerOf(Session $session, Cart $cart): bool
    {
        $customer = $session->getCustomerUser();

        return null === $cart->getCustomerId()
            || ($customer instanceof Customer && $cart->getCustomerId() === $customer->getId());
    }

    private function followTheCurrencyOf(Session $session, Cart $cart): void
    {
        if (null !== $cart->getCurrency()) {
            $session->setCurrency($cart->getCurrency());
        }
    }
}
