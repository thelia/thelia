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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\ApiTestCase;

/**
 * Both API login endpoints stop answering after a few failed attempts from the
 * same caller, and say when to come back.
 *
 * The refusal has to read the same whether the identifier names an account or
 * not: a limiter that only fires on real logins answers the question "does this
 * account exist" for whoever is walking a list.
 *
 * Each test gets its own caller address so the counters of one never spill into
 * another: they live in the shared cache pool, which outlives a single test.
 */
final class ApiLoginThrottlingTest extends ApiTestCase
{
    private const int MAX_ATTEMPTS = 3;

    /**
     * Reserved for documentation and examples (RFC 5737), so it can never be a
     * real caller of the machine running the suite.
     */
    private const string CALLER = '192.0.2.';

    private array $previousEnv = [];

    protected function setUp(): void
    {
        // Set before the kernel boots: the container reads the value the first
        // time a limiter is built and caches it for the rest of the request.
        $this->overrideEnv('THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS', (string) self::MAX_ATTEMPTS);
        $this->overrideEnv('THELIA_API_RATE_LIMIT_LOGIN_ATTEMPTS_PER_CLIENT', '10000');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            if (null === $value) {
                unset($_ENV[$name], $_SERVER[$name]);
                continue;
            }

            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $this->previousEnv = [];

        parent::tearDown();
    }

    public function testTheAttemptAfterTheLastAllowedOneIsRefusedWithADelay(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->clearRequestStack();
        $caller = self::CALLER.'11';

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            self::assertSame(
                Response::HTTP_UNAUTHORIZED,
                $this->attemptAdminLogin($admin->getLogin(), $caller)->getStatusCode(),
                \sprintf('Attempt %d should still be answered as an ordinary failure.', $attempt),
            );
        }

        $refused = $this->attemptAdminLogin($admin->getLogin(), $caller);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        $body = self::decode($refused);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $body['code']);
        self::assertArrayHasKey('message', $body);
    }

    public function testTheFrontLoginIsThrottledToo(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->clearRequestStack();
        $caller = self::CALLER.'12';

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            self::assertSame(
                Response::HTTP_UNAUTHORIZED,
                $this->attemptFrontLogin($customer->getEmail(), $caller)->getStatusCode(),
            );
        }

        self::assertSame(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->attemptFrontLogin($customer->getEmail(), $caller)->getStatusCode(),
        );
    }

    public function testARefusalReadsTheSameWhetherTheAccountExistsOrNot(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->clearRequestStack();

        $onAnAccount = $this->exhaust($admin->getLogin(), self::CALLER.'13');
        $onNothing = $this->exhaust('no-such-administrator', self::CALLER.'14');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $onAnAccount->getStatusCode());
        self::assertSame($onAnAccount->getStatusCode(), $onNothing->getStatusCode());
        self::assertSame($onAnAccount->getContent(), $onNothing->getContent());
        self::assertSame(
            $onAnAccount->headers->get('Content-Type'),
            $onNothing->headers->get('Content-Type'),
        );
        self::assertNotNull($onAnAccount->headers->get('Retry-After'));
        self::assertNotNull($onNothing->headers->get('Retry-After'));
    }

    public function testAnOrdinaryFailureKeepsTheResponseItAlwaysHad(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->clearRequestStack();

        $response = $this->attemptAdminLogin($admin->getLogin(), self::CALLER.'15');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Invalid credentials.'],
            self::decode($response),
        );
        self::assertFalse($response->headers->has('Retry-After'));
    }

    private function exhaust(string $identifier, string $caller): Response
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $this->attemptAdminLogin($identifier, $caller);
        }

        return $this->attemptAdminLogin($identifier, $caller);
    }

    private function attemptAdminLogin(string $identifier, string $caller): Response
    {
        return $this->attemptLogin('/api/admin/login', ['username' => $identifier, 'password' => 'not-the-password'], $caller);
    }

    private function attemptFrontLogin(string $identifier, string $caller): Response
    {
        return $this->attemptLogin('/api/front/login', ['username' => $identifier, 'password' => 'not-the-password'], $caller);
    }

    private function attemptLogin(string $uri, array $credentials, string $caller): Response
    {
        $this->client->request(
            'POST',
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $caller,
            ],
            content: json_encode($credentials, \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * Creating a fixture leaves a synthetic request on the stack, which then
     * shadows the login request: Symfony reads the caller and the identifier
     * from the main request, and would count every attempt under the same key.
     * Clearing the stack puts the request under test back in first position,
     * the way a real one arrives.
     */
    private function clearRequestStack(): void
    {
        $requestStack = static::getContainer()->get(RequestStack::class);

        while (null !== $requestStack->pop()) {
        }
    }

    private function overrideEnv(string $name, string $value): void
    {
        $this->previousEnv[$name] = $_SERVER[$name] ?? null;
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    private static function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
