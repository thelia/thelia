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
