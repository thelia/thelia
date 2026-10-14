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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Webhook\WebhookRetryPolicy;

final class WebhookRetryPolicyTest extends TestCase
{
    public function testTheDelayGrowsWithEachAttempt(): void
    {
        $policy = new WebhookRetryPolicy();

        self::assertSame(60, $policy->delayAfter(1));
        self::assertSame(300, $policy->delayAfter(2));
        self::assertSame(1800, $policy->delayAfter(3));
        self::assertSame(7200, $policy->delayAfter(4));
    }

    public function testTheLastAttemptHasNoDelayAndNoRetry(): void
    {
        $policy = new WebhookRetryPolicy();

        self::assertTrue($policy->shouldRetry(4));
        self::assertFalse($policy->shouldRetry(5));
        self::assertNull($policy->delayAfter(5));
    }

    public function testAnAttemptBeyondTheLimitNeverRetries(): void
    {
        self::assertFalse((new WebhookRetryPolicy())->shouldRetry(99));
    }

    public function testAnAttemptBelowOneIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WebhookRetryPolicy())->delayAfter(0);
    }
}
