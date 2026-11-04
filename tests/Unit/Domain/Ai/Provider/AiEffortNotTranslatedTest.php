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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Domain\Ai\AiEffort;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\OllamaAdapter;
use Thelia\Domain\Ai\Provider\OpenAiAdapter;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

/**
 * The adapters that have no parameter for the effort of the merchant send nothing for it and answer as usual: no
 * `reasoning`, no `reasoning_effort`, no `think`, no `output_config`, whatever the level.
 *
 * OpenAI is among them on purpose: its bridge passes an option through but names no value, and the catalogue flags no
 * model as a reasoning one, so a `reasoning.effort` would also reach the models that refuse it (gpt-4o, gpt-4.1).
 */
final class AiEffortNotTranslatedTest extends AiProviderTestCase
{
    private const array PINNED = ['api.openai.com' => '93.184.216.34', 'api.mistral.ai' => '93.184.216.34', 'api.scaleway.ai' => '93.184.216.34'];

    private const array NATIVE_NAMES = ['effort', 'reasoning', 'reasoning_effort', 'think', 'thinking', 'output_config'];

    /**
     * @return iterable<string, array{class-string, AiProvider, string|null, MockResponse, bool}>
     */
    public static function adapters(): iterable
    {
        yield 'OpenAI' => [OpenAiAdapter::class, AiProvider::OpenAi, null, Wire::responses([Wire::responsesMessage('ok')]), false];
        yield 'Mistral' => [MistralAdapter::class, AiProvider::Mistral, null, Wire::chat('ok'), false];
        yield 'Ollama' => [OllamaAdapter::class, AiProvider::Ollama, 'http://192.168.1.20:11434', Wire::ollama('ok'), true];
        yield 'OpenAI-compatible' => [OpenAiCompatibleAdapter::class, AiProvider::Scaleway, null, Wire::chat('ok'), false];
    }

    /**
     * @param class-string $adapterClass
     */
    #[DataProvider('adapters')]
    public function testAnEffortOfTheMerchantIsNotSentAndTheCallStillSucceeds(string $adapterClass, AiProvider $provider, ?string $baseUrl, MockResponse $answer, bool $privateNetwork): void
    {
        $adapter = $this->adapter($adapterClass, $this->transport($answer), self::PINNED);

        $result = $adapter->complete($this->request(), $this->target($provider, $baseUrl, AiProvider::Ollama === $provider ? null : self::KEY, $privateNetwork, effort: AiEffort::Max));

        self::assertSame('ok', $result->text);
        self::assertSame([], array_values(array_intersect(self::keysOf($this->lastBody()), self::NATIVE_NAMES)));
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<string> every key of the body, at any depth
     */
    private static function keysOf(array $body): array
    {
        $keys = [];

        foreach ($body as $key => $value) {
            $keys[] = (string) $key;

            if (\is_array($value)) {
                $keys = [...$keys, ...self::keysOf($value)];
            }
        }

        return $keys;
    }
}
