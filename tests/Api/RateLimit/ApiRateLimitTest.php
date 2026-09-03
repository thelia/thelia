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
 * How fast the API answers the same caller, outside the login endpoints.
 *
 * Three budgets, not one: an anonymous caller costs nothing to create and is
 * counted by address, an authenticated one is counted by account and gets more
 * room, and the administration surface gets the most because one back-office
 * screen fans out into several calls. A single ceiling for all three would
 * refuse a legitimate synchronisation at the same time as an abusive caller.
 */
final class ApiRateLimitTest extends ApiTestCase
{
    use RateLimitTestEnvironment;

    private const int ANONYMOUS_LIMIT = 3;

    private const string CALLER = '192.0.2.';

    protected function setUp(): void
    {
        $this->setLimit('THELIA_API_RATE_LIMIT_ANONYMOUS', (string) self::ANONYMOUS_LIMIT);
        $this->setLimit('THELIA_API_RATE_LIMIT_FRONT_AUTHENTICATED', '500');
        $this->setLimit('THELIA_API_RATE_LIMIT_ADMIN', '500');

        parent::setUp();

        $this->clearRateLimiterCounters();
    }

    protected function tearDown(): void
    {
        $this->restoreLimits();

        parent::tearDown();
    }

    public function testTheCallAfterTheLastAllowedOneIsRefusedWithADelay(): void
    {
        $caller = self::CALLER.'21';

        for ($call = 1; $call <= self::ANONYMOUS_LIMIT; ++$call) {
            self::assertSame(
                Response::HTTP_OK,
                $this->read('/api/front/products', $caller)->getStatusCode(),
                \sprintf('Call %d is still within the budget.', $call),
            );
        }

        $refused = $this->read('/api/front/products', $caller);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));

        $body = json_decode((string) $refused->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $body['code']);
        self::assertArrayHasKey('message', $body);
    }

    public function testTheBudgetIsSharedAcrossTheWholeApiSurface(): void
    {
        $caller = self::CALLER.'22';

        $this->read('/api/front/products', $caller);
        $this->read('/api/front/categories', $caller);
        $this->read('/api/front/brands', $caller);

        self::assertSame(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->read('/api/front/contents', $caller)->getStatusCode(),
        );
    }

    public function testAnExemptCallerIsNeverRefused(): void
    {
        $caller = self::CALLER.'23';
        $this->setLimit('THELIA_API_RATE_LIMIT_ALLOWLIST', $caller.',203.0.113.0/24');

        for ($call = 1; $call <= self::ANONYMOUS_LIMIT * 3; ++$call) {
            self::assertSame(
                Response::HTTP_OK,
                $this->read('/api/front/products', $caller)->getStatusCode(),
                \sprintf('Call %d should not be refused for an exempt caller.', $call),
            );
        }
    }

    public function testAnAuthenticatedAdministratorIsCountedOnItsOwnBudget(): void
    {
        $token = $this->authenticateAsAdmin();
        $this->clearRequestStack();
        $this->clearRateLimiterCounters();
        $caller = self::CALLER.'24';

        for ($call = 1; $call <= self::ANONYMOUS_LIMIT * 3; ++$call) {
            self::assertSame(
                Response::HTTP_OK,
                $this->read('/api/admin/products', $caller, $token)->getStatusCode(),
                \sprintf('Call %d is inside the administration budget.', $call),
            );
        }
    }

    public function testAnAuthenticatedCustomerIsCountedOnItsOwnBudget(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $this->clearRequestStack();
        $token = $this->authenticateAsCustomer($customer);
        $this->clearRateLimiterCounters();
        $caller = self::CALLER.'25';

        for ($call = 1; $call <= self::ANONYMOUS_LIMIT * 3; ++$call) {
            self::assertSame(
                Response::HTTP_OK,
                $this->read('/api/front/account/addresses', $caller, $token)->getStatusCode(),
                \sprintf('Call %d is inside the customer budget.', $call),
            );
        }
    }

    private function read(string $uri, string $caller, ?string $token = null): Response
    {
        $server = [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'REMOTE_ADDR' => $caller,
        ];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request('GET', $uri, server: $server);

        return $this->client->getResponse();
    }
}
