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

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Core\Security\GuestToken;
use Thelia\Model\Customer;

/**
 * What the guest checkout token of the current caller says.
 *
 * Read from the authenticated security token rather than from the JWT string:
 * {@see \Thelia\Core\EventListener\JwtListener::onAuthenticationTokenCreated()} has
 * already verified the signature and copied the claims across, so nothing here has to
 * trust — or parse — anything the caller sent.
 */
final readonly class GuestTokenClaims
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function isGuest(): bool
    {
        return \in_array(GuestToken::ROLE, $this->tokenStorage->getToken()?->getRoleNames() ?? [], true);
    }

    /**
     * The one cart this guest token was issued for, or null when it names none.
     */
    public function cartId(): ?int
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token || !$token->hasAttribute(GuestToken::CART_TOKEN_ATTRIBUTE)) {
            return null;
        }

        $cartId = $token->getAttribute(GuestToken::CART_TOKEN_ATTRIBUTE);

        return \is_int($cartId) ? $cartId : null;
    }

    /**
     * The guest account the current token belongs to, or null when the caller is not
     * a guest.
     */
    public function customer(): ?Customer
    {
        if (!$this->isGuest()) {
            return null;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof Customer ? $user : null;
    }
}
