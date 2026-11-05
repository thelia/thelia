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

namespace Thelia\Domain\Catalog\Stock;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Api\Security\CartOwnership;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;

/**
 * The cart of the visitor being served, for the readers that only want to know which cart not to
 * hold against them (the catalogue, the API reads): looked up without ever opening a session, and
 * once per request.
 *
 * Only reads what is already there. A visitor whose session is not started has no cart here: the
 * session cart is never created or restored (that starts a session, sends a cookie and dispatches
 * the restore of the cart), and a read of the catalogue must not do any of it. The cart is, in
 * this order:
 *  - the cart a guest token names (the one cart a guest may act on);
 *  - the cart of the session, when it is started and holds one;
 *  - the latest cart of the customer the API token authenticates (the cart the checkout of the
 *    API works on: the rule that scopes the cart resources of the front API, {@see CartOwnership}).
 *
 * Nothing is looked up when the shop does not reserve stock: the cart changes nothing to an answer.
 */
final class VisitorCart implements ResetInterface
{
    private bool $resolved = false;

    private ?Cart $cart = null;

    private ?Request $resolvedFor = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CartOwnership $cartOwnership,
        private readonly StockSettings $stockSettings,
    ) {
    }

    public function current(): ?Cart
    {
        if (!$this->stockSettings->reservationEnabled()) {
            return null;
        }

        // Once per request: what a request reads of the cart does not change under it, and a long-lived
        // process (a worker) never carries the cart of one request over to the next.
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        if (!$this->resolved || $this->resolvedFor !== $request) {
            $this->cart = $this->resolve($request);
            $this->resolvedFor = $request;
            $this->resolved = true;
        }

        return $this->cart;
    }

    public function currentId(): ?int
    {
        $id = (int) $this->current()?->getId();

        return $id > 0 ? $id : null;
    }

    public function reset(): void
    {
        $this->resolved = false;
        $this->cart = null;
        $this->resolvedFor = null;
    }

    private function resolve(?Request $request): ?Cart
    {
        if ($this->cartOwnership->isGuest()) {
            $cartId = $this->cartOwnership->guestCartId();

            return null === $cartId ? null : CartQuery::create()->findPk($cartId);
        }

        $cartId = $this->startedSessionCartId($request);

        if (null !== $cartId) {
            return CartQuery::create()->findPk($cartId);
        }

        $customerId = $this->cartOwnership->customerId();

        return null === $customerId ? null : CartQuery::create()->filterByCustomerId($customerId)->orderById('DESC')->findOne();
    }

    private function startedSessionCartId(?Request $request): ?int
    {
        if (null === $request || !$request->hasSession(true)) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            return null;
        }

        $cartId = $session->get(Session::SESSION_CART_ID_NAME);

        return \is_int($cartId) ? $cartId : null;
    }
}
