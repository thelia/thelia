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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\RateLimit\RateLimitTestEnvironment;

/**
 * `POST /api/front/customers` answers "This email already exists." for a registered address,
 * so it is a probe for which addresses have an account. Its own budget, per caller and per
 * address, is what stands between a stranger and that list: every attempt spends it,
 * successful or not, before the address is looked up.
 *
 * The figures are the ones of the guest registration: 20 an hour per caller, 5 an hour per address.
 */
final class CustomerRegistrationRateLimitTest extends ApiTestCase
{
    use RateLimitTestEnvironment;

    private const int PER_CLIENT = 20;
    private const int PER_ADDRESS = 5;

    private string $previousEmailConfirmation = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousEmailConfirmation = (string) ConfigQuery::read('customer_email_confirmation', '0');
        ConfigQuery::write('customer_email_confirmation', '0');
        $this->clearRateLimiterCounters();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('customer_email_confirmation', $this->previousEmailConfirmation);
        $this->clearRateLimiterCounters();

        parent::tearDown();
    }

    public function testTheCallerIsRefusedOnceItHasSpentItsBudgetWhateverTheAddresses(): void
    {
        for ($attempt = 1; $attempt <= self::PER_CLIENT; ++$attempt) {
            $response = $this->register('walker-'.$attempt.'@test.com', '198.51.100.10');
            self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $attempt.': '.$response->getContent());
        }

        $refused = $this->register('walker-last@test.com', '198.51.100.10');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertNull(
            CustomerQuery::create()->findOneByEmail('walker-last@test.com'),
            'A refused attempt creates nothing.',
        );

        // The budget is the caller's: another one is not held to it.
        self::assertSame(
            Response::HTTP_CREATED,
            $this->register('walker-other-caller@test.com', '198.51.100.11')->getStatusCode(),
        );
    }

    public function testAnAddressIsRefusedOnceItsBudgetIsSpentWhoeverAsks(): void
    {
        // The first attempt creates the account, the next ones are refused as "already exists":
        // all of them are attempts, and the callers change so the per-caller budget never trips.
        for ($attempt = 1; $attempt <= self::PER_ADDRESS; ++$attempt) {
            $this->register('Target@Test.com', $attempt % 2 ? '198.51.100.20' : '198.51.100.21');
        }

        $refused = $this->register('target@test.com', '198.51.100.22');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode(), (string) $refused->getContent());
        self::assertStringNotContainsString('already exists', (string) $refused->getContent());
    }

    public function testAnotherAddressStillPassesWhenOneIsSpent(): void
    {
        for ($attempt = 1; $attempt <= self::PER_ADDRESS; ++$attempt) {
            $this->register('spent@test.com', '198.51.100.30');
        }
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $this->register('spent@test.com', '198.51.100.31')->getStatusCode());

        self::assertSame(
            Response::HTTP_CREATED,
            $this->register('fresh@test.com', '198.51.100.31')->getStatusCode(),
        );
    }

    public function testAFailedAttemptSpendsTheBudgetToo(): void
    {
        $this->register('known@test.com', '198.51.100.40');

        // Every one of these is an "already exists" 422: refusing nothing, they must still count.
        for ($attempt = 2; $attempt <= self::PER_ADDRESS; ++$attempt) {
            $response = $this->register('known@test.com', '198.51.100.40');
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        }

        self::assertSame(
            Response::HTTP_TOO_MANY_REQUESTS,
            $this->register('known@test.com', '198.51.100.40')->getStatusCode(),
        );
    }

    public function testARegisteredAndAnUnknownAddressAreRefusedAlikeOnceTheCallerIsOverBudget(): void
    {
        $this->register('registered@test.com', '198.51.100.50');

        for ($attempt = 2; $attempt <= self::PER_CLIENT; ++$attempt) {
            $this->register('filler-'.$attempt.'@test.com', '198.51.100.50');
        }

        $registered = $this->register('registered@test.com', '198.51.100.50');
        $unknown = $this->register('unknown@test.com', '198.51.100.50');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $registered->getStatusCode());
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $unknown->getStatusCode());
        self::assertSame($unknown->getContent(), $registered->getContent());
    }

    private function register(string $email, string $caller): Response
    {
        $this->clearRequestStack();

        $this->client->request(
            'POST',
            '/api/front/customers',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'REMOTE_ADDR' => $caller,
            ],
            content: json_encode([
                'firstname' => 'Reg',
                'lastname' => 'Istrant',
                'email' => $email,
                'password' => 'secret123',
            ], \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }
}
