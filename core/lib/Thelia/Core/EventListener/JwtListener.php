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

namespace Thelia\Core\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Thelia\Core\Security\GuestToken;
use Thelia\Model\Customer;

class JwtListener
{
    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof ActiveRecordInterface) {
            return;
        }

        $payload = $event->getData();
        $payload['type'] = $user::class;

        $event->setData($payload);
    }

    /**
     * Narrows a guest checkout token down to what a guest may do.
     *
     * The bundle builds the authenticated token from Customer::getRoles(), which
     * answers ROLE_CUSTOMER for every row: left alone, a token minted for a guest
     * would open the /api/front/account endpoints to someone who never chose a
     * password. The roles claim written into the JWT is not consulted anywhere else,
     * so the substitution has to happen here, before the token becomes effective.
     *
     * Both sides are checked. A row that is still a guest never gets ROLE_CUSTOMER,
     * whatever the token says; and a token minted as a guest stays a guest token,
     * even once the account behind it has been completed — from that point on the
     * orders are reached by signing in, with a token issued to a real account.
     */
    #[AsEventListener(event: AuthenticationTokenCreatedEvent::class)]
    public function onAuthenticationTokenCreated(AuthenticationTokenCreatedEvent $event): void
    {
        $token = $event->getAuthenticatedToken();

        if (!$token instanceof JWTPostAuthenticationToken) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        $payload = $event->getPassport()->getAttribute('payload');
        $payload = \is_array($payload) ? $payload : [];
        $claimedRoles = \is_array($payload['roles'] ?? null) ? $payload['roles'] : [];

        if (!$user->isGuest() && !\in_array(GuestToken::ROLE, $claimedRoles, true)) {
            return;
        }

        $guestToken = new JWTPostAuthenticationToken(
            $user,
            $token->getFirewallName(),
            [GuestToken::ROLE],
            $token->getCredentials(),
        );

        $guestToken->setAttributes($token->getAttributes());

        // Absent rather than zero when the token names no cart: a guest token that
        // was issued without one must own no cart at all, not the cart numbered 0.
        $guestToken->setAttribute(
            GuestToken::CART_TOKEN_ATTRIBUTE,
            isset($payload[GuestToken::CART_CLAIM]) ? (int) $payload[GuestToken::CART_CLAIM] : null,
        );

        $event->setAuthenticatedToken($guestToken);
    }
}
