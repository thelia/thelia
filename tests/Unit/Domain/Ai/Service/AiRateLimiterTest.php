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

namespace Thelia\Tests\Unit\Domain\Ai\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiRateLimiter;

final class AiRateLimiterTest extends TestCase
{
    private AiRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new AiRateLimiter(new ArrayAdapter());
    }

    public function testTheDefaultOfTheAudienceAppliesWithoutAFigureOnTheUsage(): void
    {
        for ($request = 1; $request <= 10; ++$request) {
            self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, null, null), 'request '.$request);
        }

        $retryAfter = $this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, null, null);

        self::assertInstanceOf(\DateTimeImmutable::class, $retryAfter);
        self::assertGreaterThan(new \DateTimeImmutable(), $retryAfter);
        self::assertLessThanOrEqual(new \DateTimeImmutable('+61 seconds'), $retryAfter);
    }

    public function testTheFigureOfTheUsageReplacesTheDefault(): void
    {
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 2, null));
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 2, null));
        self::assertNotNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 2, null));

        for ($request = 1; $request <= 60; ++$request) {
            self::assertNull($this->limiter->consume('aiprobe.writing', 'admin-a', AiAudience::Admin, null, null));
        }
        self::assertNotNull($this->limiter->consume('aiprobe.writing', 'admin-a', AiAudience::Admin, null, null));
        self::assertNull($this->limiter->consume('aiprobe.writing', 'admin-b', AiAudience::Admin, 200, null));
    }

    public function testTheDailyWindowStopsAnActorWhoStaysUnderTheMinuteLimit(): void
    {
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 100, 3));
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 100, 3));
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 100, 3));

        $retryAfter = $this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 100, 3);

        self::assertNotNull($retryAfter);
        self::assertGreaterThan(new \DateTimeImmutable('+1 hour'), $retryAfter);
    }

    public function testActorsAndUsagesAreCountedApart(): void
    {
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 1, null));
        self::assertNotNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 1, null));
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-b', AiAudience::Public, 1, null));
        self::assertNull($this->limiter->consume('aiprobe.other', 'visitor-a', AiAudience::Public, 1, null));
    }

    public function testAJobWithoutPersonHasNoDefaultLimit(): void
    {
        for ($request = 1; $request <= 500; ++$request) {
            self::assertNull($this->limiter->consume('aiprobe.job', 'system-a', AiAudience::System, null, null));
        }

        // The merchant can still set a figure on it.
        self::assertNull($this->limiter->consume('aiprobe.job2', 'system-a', AiAudience::System, 1, null));
        self::assertNotNull($this->limiter->consume('aiprobe.job2', 'system-a', AiAudience::System, 1, null));
    }

    public function testAFigureBelowOneIsNotALimitOfZero(): void
    {
        self::assertNull($this->limiter->consume('aiprobe.chat', 'visitor-a', AiAudience::Public, 0, -5));
    }

    public function testTheActorIsHashedAsTheContractExampleSays(): void
    {
        $hasher = new AiActorHasher('ThisTokenIsNotSoSecretChangeIt');

        self::assertSame('bdb64122bc45f5fdbdc658243c3fa164e05c5eb7c06ab510f59e31a860d6ca06', $hasher->hash(AiActor::admin(1)));
        self::assertSame('14290a481a9b6d5d56f08760e17617f752e093342480f2671369b4faa560c2f8', $hasher->hash(AiActor::system('pricewatch:collect')));
        self::assertSame('65bdd52e0974b8db57afd8b7204e5e92668f7122136bfab0d139b6368078db04', $hasher->hash(AiActor::visitor('k3v9q2c8m1x7r5t0b4n6')));
        self::assertNotSame($hasher->hash(AiActor::admin(1)), $hasher->hash(AiActor::customer(1)));
        self::assertNotSame($hasher->hash(AiActor::admin(1)), (new AiActorHasher('another'))->hash(AiActor::admin(1)));
    }
}
