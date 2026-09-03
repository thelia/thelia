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

namespace Thelia\Tests\Unit\Core\Security\RateLimiter;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Security\RateLimiter\RateLimitAllowlist;

final class RateLimitAllowlistTest extends TestCase
{
    public function testAnEmptySettingExemptsNobody(): void
    {
        $allowlist = new RateLimitAllowlist('');

        self::assertFalse($allowlist->exempts('10.0.0.1'));
        self::assertFalse($allowlist->exempts('127.0.0.1'));
    }

    public function testASingleAddressIsExempt(): void
    {
        $allowlist = new RateLimitAllowlist('203.0.113.7');

        self::assertTrue($allowlist->exempts('203.0.113.7'));
        self::assertFalse($allowlist->exempts('203.0.113.8'));
    }

    public function testARangeExemptsEveryAddressInIt(): void
    {
        $allowlist = new RateLimitAllowlist('203.0.113.0/24');

        self::assertTrue($allowlist->exempts('203.0.113.1'));
        self::assertTrue($allowlist->exempts('203.0.113.254'));
        self::assertFalse($allowlist->exempts('203.0.114.1'));
    }

    public function testSeveralEntriesAreReadEvenWhenTheSettingIsUntidy(): void
    {
        $allowlist = new RateLimitAllowlist(' 203.0.113.7 , ,2001:db8::/32,  198.51.100.0/24 ');

        self::assertTrue($allowlist->exempts('203.0.113.7'));
        self::assertTrue($allowlist->exempts('2001:db8::1'));
        self::assertTrue($allowlist->exempts('198.51.100.42'));
        self::assertFalse($allowlist->exempts('192.0.2.1'));
    }

    public function testACallerWithNoAddressIsNeverExempt(): void
    {
        $allowlist = new RateLimitAllowlist('203.0.113.0/24');

        self::assertFalse($allowlist->exempts(null));
        self::assertFalse($allowlist->exempts(''));
    }
}
