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

namespace Thelia\Tests\Unit\Domain\Company;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Thelia\Domain\Company\Service\CompanyInvitationLimiter;

final class CompanyInvitationLimiterTest extends TestCase
{
    public function testTheCompanyStopsBeingAllowedOnceItsWindowIsSpent(): void
    {
        $limiter = new CompanyInvitationLimiter($this->window(2), $this->window(100), $this->requestFrom('203.0.113.7'));

        self::assertTrue($limiter->allows(1, null));
        self::assertTrue($limiter->allows(1, null));
        self::assertFalse($limiter->allows(1, null));
        self::assertTrue($limiter->allows(2, null), 'Another company has its own window.');
    }

    public function testOneCallerWalkingManyCompaniesIsStoppedByTheClientWindow(): void
    {
        $limiter = new CompanyInvitationLimiter($this->window(100), $this->window(2), $this->requestFrom('203.0.113.7'));

        self::assertTrue($limiter->allows(1, null));
        self::assertTrue($limiter->allows(2, null));
        self::assertFalse($limiter->allows(3, null));
    }

    public function testACallerOverItsWindowDoesNotSpendTheBudgetOfTheCompany(): void
    {
        $perCompany = $this->window(2);
        $limiter = new CompanyInvitationLimiter($perCompany, $this->window(1), $this->requestFrom('203.0.113.7'));

        self::assertTrue($limiter->allows(1, null));
        self::assertFalse($limiter->allows(1, null));
        self::assertFalse($limiter->allows(1, null));

        // Another caller finds the second invitation of the company still there.
        $other = new CompanyInvitationLimiter($perCompany, $this->window(1), $this->requestFrom('198.51.100.9'));
        self::assertTrue($other->allows(1, null));
        self::assertFalse($other->allows(1, null), 'And then the company is spent.');
    }

    public function testWithoutARequestTheIssuerIsTheCaller(): void
    {
        $limiter = new CompanyInvitationLimiter($this->window(100), $this->window(1), new RequestStack());

        self::assertTrue($limiter->allows(1, 5));
        self::assertFalse($limiter->allows(2, 5));
        self::assertTrue($limiter->allows(2, 6));
    }

    public function testACommandWithoutAnIssuerIsHeldToTheCompanyWindowOnly(): void
    {
        $limiter = new CompanyInvitationLimiter($this->window(2), $this->window(1), new RequestStack());

        self::assertTrue($limiter->allows(1, null));
        self::assertTrue($limiter->allows(1, null));
        self::assertFalse($limiter->allows(1, null));
    }

    private function window(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }

    private function requestFrom(string $ip): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('http://localhost', server: ['REMOTE_ADDR' => $ip]));

        return $stack;
    }
}
