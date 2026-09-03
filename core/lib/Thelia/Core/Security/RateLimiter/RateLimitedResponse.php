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

namespace Thelia\Core\Security\RateLimiter;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * The one answer every API rate limit gives.
 *
 * It carries no detail about what was refused, which account was named or how
 * much budget is left: a caller walking a list of identifiers must not be able
 * to read anything back from the refusal. The delay is the only thing it says,
 * because a client that cannot know when to come back retries immediately and
 * the shop pays for it twice.
 *
 * The shape matches the one the JWT login endpoints already answer failures
 * with, so a client parses a refusal and a bad password the same way.
 */
final class RateLimitedResponse
{
    /**
     * The request attribute a limiter leaves the delay in, for the authentication
     * failure handler to pick up: the exception Symfony's login throttling throws
     * only carries a duration rounded up to whole minutes.
     */
    public const string RETRY_AFTER_ATTRIBUTE = '_thelia_rate_limit_retry_after';

    /**
     * A client with no delay to read waits a whole window before retrying, so a
     * missing figure has to err on the safe side rather than invite an immediate
     * retry.
     */
    private const int FALLBACK_DELAY = 60;

    public static function forRateLimit(RateLimit $rateLimit): JsonResponse
    {
        return self::afterSeconds(self::secondsUntil($rateLimit->getRetryAfter()));
    }

    public static function forRequest(Request $request): JsonResponse
    {
        $retryAfter = $request->attributes->get(self::RETRY_AFTER_ATTRIBUTE);

        return self::afterSeconds(
            $retryAfter instanceof \DateTimeInterface ? self::secondsUntil($retryAfter) : self::FALLBACK_DELAY,
        );
    }

    public static function remember(Request $request, RateLimit $rateLimit): void
    {
        $request->attributes->set(self::RETRY_AFTER_ATTRIBUTE, $rateLimit->getRetryAfter());
    }

    private static function afterSeconds(int $seconds): JsonResponse
    {
        return new JsonResponse(
            [
                'code' => JsonResponse::HTTP_TOO_MANY_REQUESTS,
                'message' => 'Too many requests. Please retry later.',
            ],
            JsonResponse::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $seconds],
        );
    }

    private static function secondsUntil(\DateTimeInterface $retryAfter): int
    {
        return max(1, $retryAfter->getTimestamp() - time());
    }
}
