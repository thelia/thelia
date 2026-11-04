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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiEffort;
use Thelia\Domain\Ai\AiProvider;

final class AiEffortTest extends TestCase
{
    public function testTheFiveLevelsOfAnthropicAreTheOnlyLevels(): void
    {
        self::assertSame(['low', 'medium', 'high', 'xhigh', 'max'], array_column(AiEffort::cases(), 'value'));
    }

    public function testTheEffortOfTheUsageWinsOverTheOneOfItsTier(): void
    {
        self::assertSame(AiEffort::Low, AiEffort::resolve('theliacms.text', 'low', 'high'));
    }

    public function testAUsageWithoutEffortFollowsItsTier(): void
    {
        self::assertSame(AiEffort::High, AiEffort::resolve('theliacms.text', null, 'high'));
        self::assertSame(AiEffort::High, AiEffort::resolve('theliacms.text', '', 'high'));
    }

    public function testNoEffortAnywhereIsNoEffort(): void
    {
        self::assertNull(AiEffort::resolve('theliacms.text', null, null));
    }

    public function testAStoredValueThatIsNotALevelIsIgnoredSoTheNextOneApplies(): void
    {
        self::assertSame(AiEffort::Medium, AiEffort::resolve('theliacms.text', 'turbo', 'medium'));
        self::assertNull(AiEffort::resolve('theliacms.text', 'turbo', 'ultra'));
    }

    #[DataProvider('internalUsages')]
    public function testTheInternalCallsOfTheCoreNeverInheritAnEffort(string $usageId): void
    {
        self::assertNull(AiEffort::resolve($usageId, 'max', 'max'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function internalUsages(): iterable
    {
        yield 'the connection test' => ['core.connection_test'];
        yield 'the judge of the topic guardrail' => ['core.guardrail_topic'];
    }

    public function testOnlyAnthropicTranslatesTheEffort(): void
    {
        foreach (AiProvider::cases() as $provider) {
            self::assertSame(AiProvider::Anthropic === $provider, AiEffort::isTranslatedFor($provider), $provider->value);
        }
    }
}
