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
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Provider\OpenAiAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class OpenAiAdapterTest extends AiProviderTestCase
{
    private const array PINNED = ['api.openai.com' => '93.184.216.34', 'eu.api.openai.com' => '93.184.216.34', 'us.api.openai.com' => '93.184.216.34'];

    /** @return iterable<string, array{string, string}> */
    public static function regions(): iterable
    {
        yield 'the world endpoint' => ['https://api.openai.com', 'https://api.openai.com/v1/responses'];
        yield 'the European endpoint' => ['https://eu.api.openai.com', 'https://eu.api.openai.com/v1/responses'];
        yield 'the American endpoint' => ['https://us.api.openai.com', 'https://us.api.openai.com/v1/responses'];
    }

    #[DataProvider('regions')]
    public function testTheRegionOfTheConnectionPicksTheEndpoint(string $baseUrl, string $expected): void
    {
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport(Wire::responses([Wire::responsesMessage('ok')])), self::PINNED);

        $adapter->complete($this->request(), $this->target(AiProvider::OpenAi, $baseUrl));

        self::assertSame($expected, $this->sent[0]['url']);
    }

    public function testAnotherAddressIsRefusedWithAHintToTheCompatibleConnection(): void
    {
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport(Wire::responses([Wire::responsesMessage('ok')])));

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::OpenAi, 'https://llm.example.com'));
            self::fail('The call should have been refused.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertFalse($exception->retryable);
            self::assertStringContainsString('OpenAI-compatible', $exception->getMessage());
        }

        self::assertSame([], $this->sent, 'nothing left the machine');
    }

    public function testAKeyThatIsNotAnOpenAiKeyIsRefusedWithoutBeingEchoed(): void
    {
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport(Wire::responses([Wire::responsesMessage('ok')])), self::PINNED);

        try {
            $adapter->complete($this->request(), $this->target(AiProvider::OpenAi, null, 'scw-secret-0123456789'));
            self::fail('The call should have been refused.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertStringContainsString('sk-', $exception->getMessage());
            self::assertStringNotContainsString('scw-secret-0123456789', $exception->getMessage());
            self::assertNotInstanceOf(\Throwable::class, $exception->getPrevious());
        }

        self::assertSame([], $this->sent);
    }

    public function testCachedTokensAreTakenOutOfTheInputTokens(): void
    {
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport(Wire::responses([Wire::responsesMessage('ok')], 120, 9, 100)), self::PINNED);

        $result = $adapter->complete($this->request(), $this->target(AiProvider::OpenAi, null));

        self::assertEquals(new AiTokenUsage(20, 9, 100), $result->tokenUsage);
    }

    public function testAnAnswerCutByTheOutputLimitSaysSo(): void
    {
        $incomplete = Wire::json(['id' => 'resp_1', 'object' => 'response', 'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'model' => 'gpt-probe-2026', 'output' => [Wire::responsesMessage('Half of')], 'usage' => ['input_tokens' => 5, 'output_tokens' => 8000, 'total_tokens' => 8005]]);
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport($incomplete), self::PINNED);

        $result = $adapter->complete($this->request(), $this->target(AiProvider::OpenAi, null));

        self::assertSame(AiFinishReason::MaxTokens, $result->finishReason);
        self::assertSame('Half of', $result->text);
    }

    public function testTheSchemaIsSentStrictOnlyWhenItClosesTheObject(): void
    {
        $adapter = $this->adapter(OpenAiAdapter::class, $this->transport([Wire::responses([Wire::responsesMessage('{"a":"b"}')]), Wire::responses([Wire::responsesMessage('{"a":"b"}')])]), self::PINNED);
        $target = $this->target(AiProvider::OpenAi, null);

        $adapter->complete($this->request(outputSchema: new AiOutputSchema('page', ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a'], 'additionalProperties' => false])), $target);
        self::assertTrue($this->lastBody()['text']['format']['strict']);

        $adapter->complete($this->request(outputSchema: new AiOutputSchema('page', ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]])), $target);
        self::assertFalse($this->lastBody()['text']['format']['strict']);
    }
}
