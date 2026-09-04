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

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Thelia\Core\Security\GuestToken;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

/**
 * Hands a guest the only credential it will ever have.
 *
 * The token is an ordinary customer JWT — a guest is a real customer row, and the
 * regular provider loads it — narrowed on two axes: it grants ROLE_GUEST alone, and it
 * names the single cart it may act on. It also expires much sooner than an account
 * token: it is given to a visitor the shop knows nothing about, and it only has to
 * outlive a checkout.
 */
final readonly class GuestTokenIssuer
{
    public function __construct(
        private JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    public function issueFor(Customer $guest, ?Cart $cart): string
    {
        $payload = [
            // createFromPayload() merges this over Customer::getRoles(), so the token
            // carries the guest role and nothing else.
            'roles' => [GuestToken::ROLE],
            'exp' => time() + $this->lifetimeInSeconds(),
        ];

        if (null !== $cart?->getId()) {
            $payload[GuestToken::CART_CLAIM] = $cart->getId();
        }

        return $this->jwtTokenManager->createFromPayload($guest, $payload);
    }

    public function lifetimeInSeconds(): int
    {
        $configured = (int) ConfigQuery::read(
            GuestToken::LIFETIME_CONFIG_KEY,
            (string) GuestToken::DEFAULT_LIFETIME_IN_SECONDS,
        );

        // A lifetime of zero or less would hand out tokens nothing can accept, which
        // reads as a broken shop rather than as a strict one.
        return $configured > 0 ? $configured : GuestToken::DEFAULT_LIFETIME_IN_SECONDS;
    }
}
