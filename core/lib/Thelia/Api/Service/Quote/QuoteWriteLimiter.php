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

namespace Thelia\Api\Service\Quote;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The budget of an account for what it writes on the quotes of the shop: asking for a quote copies a
 * whole cart into the database, and a counter-proposal wakes the shop up. A budget each, counted per
 * account, whose figures come from the environment (see parameters/api_rate_limit.php).
 */
final readonly class QuoteWriteLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.front_quote_request')]
        private RateLimiterFactoryInterface $requests,
        #[Autowire(service: 'limiter.front_quote_counter_offer')]
        private RateLimiterFactoryInterface $counterOffers,
    ) {
    }

    /**
     * @throws TooManyRequestsHttpException
     */
    public function spendRequest(int $customerId): void
    {
        $this->spend($this->requests, $customerId, 'Too many requests for a quote. Please retry later.');
    }

    /**
     * @throws TooManyRequestsHttpException
     */
    public function spendCounterOffer(int $customerId): void
    {
        $this->spend($this->counterOffers, $customerId, 'Too many counter-proposals. Please retry later.');
    }

    private function spend(RateLimiterFactoryInterface $factory, int $customerId, string $message): void
    {
        $limit = $factory->create((string) $customerId)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), $message);
        }
    }
}
