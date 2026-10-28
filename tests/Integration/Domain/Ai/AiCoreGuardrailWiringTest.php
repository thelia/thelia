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

namespace Thelia\Tests\Integration\Domain\Ai;

use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiTier;
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Domain\Ai\Guardrail\Moderation\MistralModerationGuardrail;
use Thelia\Domain\Ai\Guardrail\Moderation\OpenAiModerationGuardrail;
use Thelia\Domain\Ai\Guardrail\TopicGuardrail;
use Thelia\Test\IntegrationTestCase;

/**
 * The guardrails the core ships are discovered by the runner, and the usage of the topic judge is declared.
 */
final class AiCoreGuardrailWiringTest extends IntegrationTestCase
{
    public function testTheThreeOptionalGuardrailsOfTheCoreAreInstalled(): void
    {
        $availability = $this->getService(AiGuardrailRunner::class)->availability();

        self::assertArrayHasKey(MistralModerationGuardrail::NAME, $availability);
        self::assertArrayHasKey(OpenAiModerationGuardrail::NAME, $availability);
        self::assertArrayHasKey(TopicGuardrail::NAME, $availability);
    }

    public function testTheTopicJudgeDeclaresItsUsageToTheRegistry(): void
    {
        $usage = $this->getService(AiUsageRegistryInterface::class)->get('core.guardrail_topic');

        self::assertSame([AiAudience::System, AiTier::Fast, AiTransparency::None, []], [$usage->audience, $usage->tier, $usage->transparency, $usage->guardrails]);
        self::assertSame('core', $usage->module());
    }
}
