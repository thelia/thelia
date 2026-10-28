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

namespace Thelia\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Customer\Service\CustomerRegistrationLimiter;

/**
 * Registering says "This email already exists." for a registered address: the attempts are
 * counted per address and per caller, whatever their outcome.
 */
final class CustomerRegistrationLimiterTest extends TestCase
{
    public function testAttemptsOnOneAddressStopBeingAllowedOnceTheLimitIsReached(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(3), $this->window(100), $this->requestStackFrom('203.0.113.7'));

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertFalse($limiter->allows('someone@example.com'));
    }

    public function testTheAddressBudgetIgnoresCaseAndSurroundingSpaces(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(2), $this->window(100), $this->requestStackFrom('203.0.113.7'));

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows(' Someone@Example.COM '));
        self::assertFalse($limiter->allows('SOMEONE@EXAMPLE.COM'));
    }

    public function testOneCallerWalkingManyAddressesIsStoppedByTheClientLimit(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(100), $this->window(2), $this->requestStackFrom('203.0.113.7'));

        self::assertTrue($limiter->allows('first@example.com'));
        self::assertTrue($limiter->allows('second@example.com'));
        self::assertFalse($limiter->allows('third@example.com'));
    }

    public function testACallerOverItsLimitDoesNotSpendTheBudgetOfTheAddress(): void
    {
        $perAddress = $this->window(1);
        $requestStack = $this->requestStackFrom('203.0.113.7');
        $limiter = new CustomerRegistrationLimiter($perAddress, $this->window(1), $requestStack);

        self::assertTrue($limiter->allows('first@example.com'));
        self::assertFalse($limiter->allows('owner@example.com'));

        // The owner of the address, from elsewhere: its budget was never touched.
        $owner = new CustomerRegistrationLimiter($perAddress, $this->window(1), $this->requestStackFrom('203.0.113.8'));
        self::assertTrue($owner->allows('owner@example.com'));
    }

    public function testWithoutARequestOnlyTheAddressLimitApplies(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(2), $this->window(1), new RequestStack());

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertFalse($limiter->allows('someone@example.com'));
    }

    public function testARefusedAttemptSaysHowLongToWaitAndAnAcceptedOneSaysNothing(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(1), $this->window(100), $this->requestStackFrom('203.0.113.7'));

        self::assertNull($limiter->attempt('someone@example.com'));

        $secondsToWait = $limiter->attempt('someone@example.com');

        self::assertIsInt($secondsToWait);
        self::assertGreaterThan(0, $secondsToWait);
        self::assertLessThanOrEqual(3600, $secondsToWait);
    }

    public function testTheClientLimitAlsoSaysHowLongToWait(): void
    {
        $limiter = new CustomerRegistrationLimiter($this->window(100), $this->window(1), $this->requestStackFrom('203.0.113.7'));

        self::assertNull($limiter->attempt('first@example.com'));
        self::assertGreaterThan(0, $limiter->attempt('second@example.com'));
    }

    private function window(int $limit): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }

    private function requestStackFrom(string $clientIp): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/front/customers', 'POST', server: ['REMOTE_ADDR' => $clientIp]));

        return $requestStack;
    }
}
