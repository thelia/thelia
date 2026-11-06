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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Caps how often the stock of a cart may be held: a hold locks the stock rows and writes the
 * lines, so a caller who repeats it, on one cart or across many from one address, could keep the
 * stock out of every real buyer.
 *
 * What is capped is a line newly held, not a hold that pushes the date of lines already held (a
 * buyer who refreshes the step, or changes a quantity, pays nothing): the hold spends one unit of
 * each budget (spend()) before it writes anything, as soon as one of its lines is new. Spending
 * is the check itself: asking first and spending after would let parallel requests all pass
 * before the first one paid.
 *
 * The address is judged and spent first, so that a caller already over it stops eating the budget
 * of the cart. No request (a command, a worker) has no address: only the cart is capped then.
 */
final readonly class StockReservationLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.stock_reservation_per_cart')]
        private RateLimiterFactoryInterface $perCartLimiter,
        #[Autowire(service: 'limiter.stock_reservation_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Spends one new hold of the cart, from the budget of the address then from the one of the cart.
     *
     * @return int|null the seconds to wait before asking again when a budget is spent, null when the hold may go on
     */
    public function spend(int $cartId): ?int
    {
        foreach ($this->limiters($cartId) as $limiter) {
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                return self::secondsUntilRetry($limit);
            }
        }

        return null;
    }

    /**
     * @return list<LimiterInterface> the address first, then the cart
     */
    private function limiters(int $cartId): array
    {
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();
        $limiters = [];

        if (null !== $clientIp) {
            $limiters[] = $this->perClientLimiter->create($clientIp);
        }

        $limiters[] = $this->perCartLimiter->create((string) $cartId);

        return $limiters;
    }

    private static function secondsUntilRetry(RateLimit $limit): int
    {
        return max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
