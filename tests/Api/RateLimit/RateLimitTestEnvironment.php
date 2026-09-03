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

namespace Thelia\Tests\Api\RateLimit;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What a test needs in order to reach a rate limit on purpose.
 *
 * The suite runs with the limits raised out of the way, so a test that wants to
 * see one has to lower the one it is about to reach, before the kernel boots:
 * the container reads a limit the first time it builds the limiter and keeps the
 * value for the rest of the request.
 *
 * The counters live in a cache pool that survives the run, so they are wiped
 * between tests. Without that, a test that passes on its own fails when the
 * suite replays it inside the same minute.
 */
trait RateLimitTestEnvironment
{
    /** @var array<string, string|null> */
    private array $rateLimitEnvBackup = [];

    protected function setLimit(string $name, string $value): void
    {
        $this->rateLimitEnvBackup[$name] ??= $_SERVER[$name] ?? null;
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    protected function restoreLimits(): void
    {
        foreach ($this->rateLimitEnvBackup as $name => $value) {
            if (null === $value) {
                unset($_ENV[$name], $_SERVER[$name]);
                continue;
            }

            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $this->rateLimitEnvBackup = [];
    }

    protected function clearRateLimiterCounters(): void
    {
        $pool = static::getContainer()->get('cache.rate_limiter');

        if ($pool instanceof CacheItemPoolInterface) {
            $pool->clear();
        }
    }

    /**
     * Creating a fixture leaves a synthetic request on the stack, which then
     * shadows the request under test: Symfony reads the caller and the
     * identifier of a login attempt from the main request, and would count
     * every attempt under the same key. Clearing the stack puts the request
     * under test back in first position, the way a real one arrives.
     */
    protected function clearRequestStack(): void
    {
        $requestStack = static::getContainer()->get(RequestStack::class);

        while (null !== $requestStack->pop()) {
        }
    }
}
