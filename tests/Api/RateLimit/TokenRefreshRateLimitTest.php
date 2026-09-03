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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\ApiTestCase;

/**
 * The token refresh endpoints are counted like the login endpoints are.
 *
 * A client asks for a new access token once per token lifetime, so a caller
 * asking again and again is either misbuilt or trying refresh tokens one after
 * the other, and both are worth stopping.
 *
 * Each test uses its own caller address, since the counters live in the shared
 * cache pool and outlive a single test.
 */
final class TokenRefreshRateLimitTest extends ApiTestCase
{
    use RateLimitTestEnvironment;

    private const int MAX_REFRESHES = 2;

    private const string CALLER = '198.51.100.';

    protected function setUp(): void
    {
        $this->setLimit('THELIA_API_RATE_LIMIT_TOKEN_REFRESH', (string) self::MAX_REFRESHES);

        parent::setUp();

        $this->clearRateLimiterCounters();
    }

    protected function tearDown(): void
    {
        $this->restoreLimits();

        parent::tearDown();
    }

    public function testTheRefreshAfterTheLastAllowedOneIsRefusedWithADelay(): void
    {
        $caller = self::CALLER.'11';

        for ($attempt = 1; $attempt <= self::MAX_REFRESHES; ++$attempt) {
            self::assertSame(
                Response::HTTP_UNAUTHORIZED,
                $this->refresh('/api/admin/token/refresh', 'not-a-refresh-token', $caller)->getStatusCode(),
            );
        }

        $refused = $this->refresh('/api/admin/token/refresh', 'not-a-refresh-token', $caller);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));

        $body = json_decode((string) $refused->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $body['code']);
        self::assertArrayHasKey('message', $body);
    }

    public function testTheFrontRefreshIsCountedToo(): void
    {
        $caller = self::CALLER.'12';

        for ($attempt = 1; $attempt <= self::MAX_REFRESHES; ++$attempt) {
            $this->refresh('/api/front/token/refresh', 'not-a-refresh-token', $caller);
        }

        self::assertSame(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->refresh('/api/front/token/refresh', 'not-a-refresh-token', $caller)->getStatusCode(),
        );
    }

    public function testAnExemptCallerIsNeverRefused(): void
    {
        $caller = self::CALLER.'13';
        $this->setLimit('THELIA_API_RATE_LIMIT_ALLOWLIST', $caller);

        for ($attempt = 1; $attempt <= self::MAX_REFRESHES * 3; ++$attempt) {
            self::assertSame(
                Response::HTTP_UNAUTHORIZED,
                $this->refresh('/api/admin/token/refresh', 'not-a-refresh-token', $caller)->getStatusCode(),
                \sprintf('Refresh %d should not be refused for an exempt caller.', $attempt),
            );
        }
    }

    private function refresh(string $uri, string $refreshToken, string $caller): Response
    {
        $this->client->request(
            'POST',
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $caller,
            ],
            content: json_encode(['refresh_token' => $refreshToken], \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }
}
