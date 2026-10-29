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

namespace Thelia\Tests\Unit\Domain\Ai\Provider;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Provider\AiProviderPresets;

final class AiProviderPresetsTest extends TestCase
{
    public function testTheKeysAreReadFromTheVariablesTheFlexRecipesWrite(): void
    {
        $presets = new AiProviderPresets();

        self::assertSame('MISTRAL_API_KEY', $presets->apiKeyEnvironmentVariable(AiProvider::Mistral));
        self::assertSame('ANTHROPIC_API_KEY', $presets->apiKeyEnvironmentVariable(AiProvider::Anthropic));
        self::assertSame('OPENAI_API_KEY', $presets->apiKeyEnvironmentVariable(AiProvider::OpenAi));
        self::assertNull($presets->apiKeyEnvironmentVariable(AiProvider::Scaleway));
    }

    public function testEveryProviderEitherHasAnEndpointOrNeedsOne(): void
    {
        $presets = new AiProviderPresets();

        foreach (AiProvider::cases() as $provider) {
            $url = $presets->defaultBaseUrl($provider);

            if (null !== $url) {
                self::assertStringStartsWith('https://', $url, $provider->value);
            }
        }

        self::assertNull($presets->defaultBaseUrl(AiProvider::OpenAiCompatible));
        self::assertNull($presets->defaultBaseUrl(AiProvider::Ollama));
    }

    public function testTheCompatibleEndpointsEndWithTheVersionTheAdapterExpects(): void
    {
        $presets = new AiProviderPresets();

        foreach ([AiProvider::Scaleway, AiProvider::OvhCloud, AiProvider::Albert] as $provider) {
            self::assertStringEndsWith('/v1', (string) $presets->defaultBaseUrl($provider), $provider->value);
        }
    }

    public function testOnlyTheConnectionsThatAnswerAnonymouslyMayHaveNoKey(): void
    {
        $presets = new AiProviderPresets();

        self::assertTrue($presets->keyIsOptional(AiProvider::Ollama));
        self::assertTrue($presets->keyIsOptional(AiProvider::OpenAiCompatible));
        self::assertTrue($presets->keyIsOptional(AiProvider::OvhCloud), 'AI Endpoints answers an anonymous caller');
        self::assertFalse($presets->keyIsOptional(AiProvider::Mistral));
        self::assertFalse($presets->keyIsOptional(AiProvider::Anthropic));
        self::assertFalse($presets->keyIsOptional(AiProvider::Scaleway));
    }
}
