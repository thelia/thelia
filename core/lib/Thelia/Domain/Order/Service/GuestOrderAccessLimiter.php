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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Domain\Order\Enum\GuestOrderAccessVerdict;

/**
 * Caps how often a guest order tracking link may be presented.
 *
 * The link is the whole of the authentication: whoever holds it sees the order. The
 * signature cannot be guessed, but the order number in front of it is a small integer,
 * so an unauthenticated caller can still try one after another. Both windows are
 * needed: the per-order one keeps a single order from being hammered, the per-client
 * one stops a single caller from walking the order numbers.
 *
 * Callers must answer the visitor the same way whether this returned true or false,
 * otherwise the answer tells the caller which order numbers exist.
 */
final readonly class GuestOrderAccessLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.guest_order_access_per_order')]
        private RateLimiterFactoryInterface $perOrderLimiter,
        #[Autowire(service: 'limiter.guest_order_access_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Whether this visitor may present one more tracking token.
     *
     * Call this before the token is checked, so that a caller spends the same budget
     * whether or not the token turns out to be valid.
     */
    public function allows(string $token): bool
    {
        return GuestOrderAccessVerdict::Allowed === $this->check($token);
    }

    /**
     * The same question, with the reason attached.
     *
     * Callers that answer over HTTP need it: the per-client refusal is about the caller
     * and may be said out loud as a 429, while the per-order one is bound to an order
     * number and has to come back looking exactly like an unknown token.
     */
    public function check(string $token): GuestOrderAccessVerdict
    {
        // No request in a CLI context: the per-client limit simply does not apply.
        // It is consumed first so that a caller who is already over it stops eating the
        // budget of the order, whose legitimate owner would then be turned away.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp && !$this->perClientLimiter->create($clientIp)->consume()->isAccepted()) {
            return GuestOrderAccessVerdict::ClientBudgetExhausted;
        }

        if (!$this->perOrderLimiter->create($this->orderKey($token))->consume()->isAccepted()) {
            return GuestOrderAccessVerdict::OrderBudgetExhausted;
        }

        return GuestOrderAccessVerdict::Allowed;
    }

    /**
     * The order the token claims to name, before anything is checked.
     *
     * A malformed token has no order behind it, and all of them share one window: the
     * cap must not be something a caller can escape by sending nonsense.
     */
    private function orderKey(string $token): string
    {
        $orderId = explode('.', $token)[0];

        return ctype_digit($orderId) ? 'order-'.$orderId : 'malformed';
    }
}
