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

namespace Thelia\Domain\Ai\Tool\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * The requests of an MCP token: 60 per minute and 2 000 per day, the defaults of an administrator on the
 * gateway (AiRateLimiter::DEFAULTS). Sliding windows on the pool `cache.rate_limiter`, keyed by the id of the token.
 *
 * @internal
 */
final readonly class AiToolTokenRateLimiter
{
    public const int PER_MINUTE = 60;

    public const int PER_DAY = 2000;

    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')]
        private CacheItemPoolInterface $pool,
    ) {
    }

    /**
     * Counts one request.
     *
     * @phpstan-impure
     *
     * @return \DateTimeImmutable|null null when the request is allowed, else when the token may try again
     */
    public function consume(int $tokenId): ?\DateTimeImmutable
    {
        foreach (['minute' => [self::PER_MINUTE, '1 minute'], 'day' => [self::PER_DAY, '1 day']] as $name => [$limit, $interval]) {
            $factory = new RateLimiterFactory(['id' => 'ai_mcp_token_'.$name, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval], new CacheStorage($this->pool));
            $rate = $factory->create('token-'.$tokenId)->consume();

            if (!$rate->isAccepted()) {
                return $rate->getRetryAfter();
            }
        }

        return null;
    }
}
