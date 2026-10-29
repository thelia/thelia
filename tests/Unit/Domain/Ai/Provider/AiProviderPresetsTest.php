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

    public function testEveryProbeModelIsInTheEmbeddedCatalogueAndOpenAiOnesAreDated(): void
    {
        $presets = new AiProviderPresets();
        $path = \dirname(__DIR__, 5).'/core/lib/Thelia/Domain/Ai/Resources/model-catalog.json';
        $names = [];

        foreach (json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR)['models'] as $entry) {
            $names[$entry['provider']][$entry['model']] = true;
        }

        $probed = 0;

        foreach (AiProvider::cases() as $provider) {
            $probe = $presets->probeModel($provider);

            if (null === $probe) {
                continue;
            }

            ++$probed;
            self::assertArrayHasKey($probe, $names[$provider->value] ?? [], $provider->value.' probes a model its catalogue does not hold');
        }

        self::assertGreaterThanOrEqual(4, $probed);
        // The undated aliases of OpenAI are not served by /v1/responses in the European region.
        self::assertMatchesRegularExpression('/-\d{4}-\d{2}-\d{2}$/', (string) $presets->probeModel(AiProvider::OpenAi));
        self::assertNull($presets->probeModel(AiProvider::Ollama));
        self::assertNull($presets->probeModel(AiProvider::OpenAiCompatible));
    }
}
