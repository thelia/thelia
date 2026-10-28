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

namespace Thelia\Domain\Ai\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Thelia\Domain\Ai\AiAudience;

/**
 * How often an actor may call a usage: a sliding window of a minute and one of a day, per actor and per usage.
 *
 * The figures are those the merchant set on the usage; without them the default of the audience applies. An
 * anonymous visitor gets the narrowest, a job that works for nobody none. The limiters are those of the
 * framework (sliding window, the pool `cache.rate_limiter`) built here instead of declared in the framework
 * configuration, because a declared limiter has one fixed figure and this one changes with each usage.
 *
 * @internal
 */
final readonly class AiRateLimiter
{
    /** @var array<string, array{int|null, int|null}> requests per minute and per day by audience */
    public const array DEFAULTS = [
        'public' => [10, 100],
        'customer' => [20, 200],
        'admin' => [60, 2000],
        'system' => [null, null],
    ];

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
     * @return \DateTimeImmutable|null null when the request is allowed, else when the actor may try again
     */
    public function consume(string $usageId, string $actorHash, AiAudience $audience, ?int $perMinute, ?int $perDay): ?\DateTimeImmutable
    {
        [$defaultPerMinute, $defaultPerDay] = self::DEFAULTS[$audience->value];

        $windows = [
            'minute' => [self::limit($perMinute) ?? $defaultPerMinute, '1 minute'],
            'day' => [self::limit($perDay) ?? $defaultPerDay, '1 day'],
        ];

        foreach ($windows as $name => [$limit, $interval]) {
            if (null === $limit) {
                continue;
            }

            $factory = new RateLimiterFactory(['id' => 'ai_'.$name, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval], new CacheStorage($this->pool));
            $rate = $factory->create($usageId.'-'.$actorHash)->consume();

            if (!$rate->isAccepted()) {
                return $rate->getRetryAfter();
            }
        }

        return null;
    }

    private static function limit(?int $figure): ?int
    {
        return null !== $figure && $figure >= 1 ? $figure : null;
    }
}
