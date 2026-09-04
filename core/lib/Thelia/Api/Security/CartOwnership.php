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

namespace Thelia\Api\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Customer;

/**
 * Who the current caller's cart belongs to.
 *
 * A cart is owned either by an authenticated customer or by the visitor
 * session that opened it. Both the query extension that scopes the cart
 * endpoints and the voter that guards them read the answer here, so the two
 * layers can never disagree on what "mine" means.
 */
final readonly class CartOwnership
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private GuestTokenClaims $guestTokenClaims,
    ) {
    }

    public function customerId(): ?int
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof Customer ? $user->getId() : null;
    }

    /**
     * Whether the caller is checking out without an account.
     */
    public function isGuest(): bool
    {
        return $this->guestTokenClaims->isGuest();
    }

    /**
     * The one cart a guest caller may act on, or null when its token names none.
     */
    public function guestCartId(): ?int
    {
        return $this->guestTokenClaims->cartId();
    }

    public function sessionCartId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        // Read the visitor's cart only when a session is already open: the API
        // firewall is stateless, and starting a session here would hand every
        // anonymous read a cookie it never asked for.
        if (null === $request || !$request->hasSession(true)) {
            return null;
        }

        $cartId = $request->getSession()->get(Session::SESSION_CART_ID_NAME);

        return \is_int($cartId) ? $cartId : null;
    }

    public function ownsCart(?int $cartCustomerId, ?int $cartId): bool
    {
        // A guest owns the cart its token names and nothing else. The customer id it
        // authenticates as is not a good enough answer: the guest row behind an address
        // is reused, so it can be reached by anyone who registers as a guest with that
        // address, and the carts hanging off it would come with it.
        if ($this->guestTokenClaims->isGuest()) {
            $claimedCartId = $this->guestTokenClaims->cartId();

            return null !== $claimedCartId && $claimedCartId === $cartId;
        }

        $customerId = $this->customerId();

        if (null !== $customerId && $customerId === $cartCustomerId) {
            return true;
        }

        $sessionCartId = $this->sessionCartId();

        return null !== $sessionCartId && $sessionCartId === $cartId;
    }
}
