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
use Thelia\Domain\Customer\Service\CustomerEmailRequestLimiter;

/**
 * "Send me the activation code again" and "send me a new password" are reachable by
 * anyone who knows an address, so the number of emails they can trigger has to be
 * capped per address and per caller.
 */
final class CustomerEmailRequestLimiterTest extends TestCase
{
    public function testRequestsForOneAddressStopBeingAllowedOnceTheLimitIsReached(): void
    {
        $limiter = new CustomerEmailRequestLimiter(
            $this->window(3),
            $this->window(100),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertFalse($limiter->allows('someone@example.com'));
        self::assertFalse($limiter->allows('someone@example.com'));
    }

    public function testTheAddressLimitIgnoresCaseSoItCannotBeSidesteppedByRetyping(): void
    {
        $limiter = new CustomerEmailRequestLimiter(
            $this->window(2),
            $this->window(100),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('Someone@Example.COM'));
        self::assertFalse($limiter->allows('SOMEONE@EXAMPLE.COM'));
    }

    public function testOneCallerWalkingManyAddressesIsStoppedByTheClientLimit(): void
    {
        $limiter = new CustomerEmailRequestLimiter(
            $this->window(100),
            $this->window(2),
            $this->requestStackWithClientIp('203.0.113.7'),
        );

        self::assertTrue($limiter->allows('first@example.com'));
        self::assertTrue($limiter->allows('second@example.com'));
        self::assertFalse($limiter->allows('third@example.com'));
    }

    public function testWithoutARequestOnlyTheAddressLimitApplies(): void
    {
        // A command line caller (module, cron, install script) has no client IP:
        // the per-client limit must be skipped instead of blocking the send. Here it
        // would deny everything after the first call if it were applied.
        $limiter = new CustomerEmailRequestLimiter(
            $this->window(2),
            $this->window(1),
            new RequestStack(),
        );

        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertTrue($limiter->allows('someone@example.com'));
        self::assertFalse($limiter->allows('someone@example.com'));
    }

    private function window(int $limit): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory(
            [
                'id' => 'test',
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => '1 hour',
            ],
            new InMemoryStorage(),
        );
    }

    private function requestStackWithClientIp(string $clientIp): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/password/resend', server: ['REMOTE_ADDR' => $clientIp]));

        return $requestStack;
    }
}
