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

namespace Thelia\Tests\Unit\Domain\Ai\Catalog;

use PHPUnit\Framework\TestCase;

/**
 * What the embedded price list says where the provider documents it differently from LiteLLM, the source it is built
 * from (`Resources/build-model-catalog.py`).
 */
final class EmbeddedModelCatalogueTest extends TestCase
{
    /** @var array<string, array<string, array<string, mixed>>>|null */
    private static ?array $models = null;

    public function testScalewayModelsCarryTheIdentifierOfTheServerlessApiAndTakeStructuredOutput(): void
    {
        $scaleway = self::models()['scaleway'];

        self::assertNotSame([], $scaleway);

        foreach ($scaleway as $name => $entry) {
            self::assertStringNotContainsString('/', $name, 'api.scaleway.ai/v1 names a model without the vendor prefix');
            self::assertTrue($entry['capabilities']['structured_output'], $name);
        }

        self::assertArrayHasKey('llama-3.3-70b-instruct', $scaleway);
        self::assertArrayHasKey('gpt-oss-120b', $scaleway);
    }

    public function testTheModelsTheProvidersNoLongerServeAreNotListed(): void
    {
        $models = self::models();

        // Mistral: absent from GET /v1/models (codestral-mamba-latest answers 400 invalid_model), or an alias of another model (mistral-tiny).
        foreach (['codestral-mamba-latest', 'devstral-latest', 'devstral-medium-latest', 'devstral-small-latest', 'ministral-3-14b-2512', 'ministral-3-3b-2512', 'ministral-3-8b-2512', 'mistral-code-agent-latest', 'mistral-large-3', 'mistral-small', 'mistral-tiny', 'open-mistral-nemo', 'pixtral-large-latest'] as $name) {
            self::assertArrayNotHasKey($name, $models['mistral'], $name);
        }

        // OpenAI: deprecated (daybreak), unknown to the provider (gpt-5.5-cyber) or shut down (search previews).
        foreach (['daybreak-blue-latest', 'daybreak-red-latest', 'gpt-5.5-cyber', 'gpt-5.5-cyber-preview', 'gpt-4o-search-preview', 'gpt-4o-mini-search-preview'] as $name) {
            self::assertArrayNotHasKey($name, $models['openai'], $name);
        }

        // The models behind the probes of the connection test stay.
        self::assertArrayHasKey('ministral-3b-2512', $models['mistral']);
        self::assertArrayHasKey('gpt-5.4-nano-2026-03-17', $models['openai']);
    }

    public function testOllamaModelsTakeStructuredOutput(): void
    {
        $ollama = self::models()['ollama'];

        self::assertNotSame([], $ollama);

        foreach ($ollama as $name => $entry) {
            self::assertTrue($entry['capabilities']['structured_output'], $name);
        }
    }

    /**
     * @return array<string, array<string, array<string, mixed>>> provider => model => entry
     */
    private static function models(): array
    {
        if (null !== self::$models) {
            return self::$models;
        }

        $path = \dirname(__DIR__, 5).'/core/lib/Thelia/Domain/Ai/Resources/model-catalog.json';
        $models = [];

        foreach (json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR)['models'] as $entry) {
            $models[$entry['provider']][$entry['model']] = $entry;
        }

        return self::$models = $models;
    }
}
