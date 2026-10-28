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
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiOutputSchema;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\AiToolDefinition;
use Thelia\Domain\Ai\Provider\AiProviderResult;
use Thelia\Domain\Ai\Provider\AnthropicAdapter;
use Thelia\Tests\Support\Ai\AiProviderFixtures as Wire;
use Thelia\Tests\Support\Ai\AiProviderTestCase;

final class AnthropicAdapterTest extends AiProviderTestCase
{
    private const array PINNED = ['api.anthropic.com' => '93.184.216.34'];

    public function testTheCacheTokensAreCountedApartFromTheInputTokens(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']], usage: ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 400, 'cache_creation_input_tokens' => 30])));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::Anthropic));

        self::assertEquals(new AiTokenUsage(100, 50, 400, 30), $result->tokenUsage);
        self::assertSame('claude-probe-2026', $result->model);
    }

    public function testStreamedCacheTokensAreCountedToo(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicStream(25, 15, 400, 30)));
        $stream = $adapter->stream($this->request(), $this->target(AiProvider::Anthropic));

        foreach ($stream as $_) {
        }

        self::assertEquals(new AiTokenUsage(25, 15, 400, 30), $stream->result()->tokenUsage);
    }

    public function testTheEndOfTheSystemPromptAndTheLastTurnAreMarkedForTheCache(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport([Wire::anthropic([['type' => 'text', 'text' => 'ok']]), Wire::anthropic([['type' => 'text', 'text' => 'ok']])]));
        $target = $this->target(AiProvider::Anthropic);

        $adapter->complete($this->request(system: 'You write for a coffee shop.'), $target);
        $body = $this->lastBody();

        self::assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);
        self::assertSame(['type' => 'ephemeral'], $body['messages'][0]['content'][0]['cache_control']);

        // In a tool loop the last turn is the result of the tool.
        $call = new AiToolCall('toolu_01', 'list_categories', []);
        $adapter->complete($this->request([AiMessage::user('Which?'), AiMessage::assistant('', [$call]), AiMessage::toolResult('toolu_01', 'Chairs')], system: 'You write for a coffee shop.', tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object', 'properties' => []])]), $target);
        $body = $this->lastBody();
        $lastMessage = $body['messages'][array_key_last($body['messages'])];

        self::assertSame('user', $lastMessage['role']);
        self::assertSame('tool_result', $lastMessage['content'][0]['type']);
        self::assertSame(['type' => 'ephemeral'], $lastMessage['content'][array_key_last($lastMessage['content'])]['cache_control']);
        self::assertSame(['type' => 'ephemeral'], $body['tools'][0]['cache_control']);
    }

    public function testTheEffortOfTheContractIsWrittenWhereTheApiReadsIt(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => '{"a":"b"}']])));

        $adapter->complete($this->request(outputSchema: new AiOutputSchema('page', ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]]), providerOptions: ['anthropic' => ['effort' => 'high', 'thinking' => ['type' => 'adaptive']]]), $this->target(AiProvider::Anthropic));

        $body = $this->lastBody();

        self::assertSame('high', $body['output_config']['effort']);
        self::assertSame('json_schema', $body['output_config']['format']['type'], 'the schema and the effort live together');
        self::assertSame(['type' => 'adaptive'], $body['thinking']);
        self::assertArrayNotHasKey('effort', $body);
    }

    public function testTheThinkingBlocksOfAnAnswerComeBackSignedOnTheNextTurn(): void
    {
        $answer = Wire::anthropic([
            ['type' => 'thinking', 'thinking' => 'I should list the categories.', 'signature' => 'sig-abc'],
            ['type' => 'text', 'text' => 'Let me look.'],
            ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'list_categories', 'input' => ['limit' => 3]],
        ], 'tool_use');
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport([$answer, Wire::anthropic([['type' => 'text', 'text' => 'Done.']])]));
        $target = $this->target(AiProvider::Anthropic);
        $request = $this->request(tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object', 'properties' => []])], providerOptions: ['anthropic' => ['thinking' => ['type' => 'enabled', 'budget_tokens' => 1024]]]);

        $first = $adapter->complete($request, $target);

        self::assertSame(['provider' => 'anthropic', 'thinking' => [['content' => 'I should list the categories.', 'signature' => 'sig-abc']]], $first->providerState);

        $adapter->complete($request->withMessages(AiMessage::fromResponse($this->responseOf($first)), AiMessage::toolResult('toolu_01', 'Chairs')), $target);

        $assistant = $this->lastBody()['messages'][1];

        self::assertSame('assistant', $assistant['role']);
        self::assertSame(['thinking', 'text', 'tool_use'], array_column($assistant['content'], 'type'));
        self::assertSame('sig-abc', $assistant['content'][0]['signature']);
        self::assertSame('I should list the categories.', $assistant['content'][0]['thinking']);
    }

    public function testTheThinkingBlocksOfAStreamComeBackSignedToo(): void
    {
        // RECORDED by symfony/ai: claude-sonnet-4-5, a thinking block then a call of the tool "clock".
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport([Wire::cassette('streaming_thinking_tool_call'), Wire::anthropic([['type' => 'text', 'text' => 'Noon.']])]));
        $target = $this->target(AiProvider::Anthropic);
        $request = $this->request([AiMessage::user('What time is it right now?')], tools: [new AiToolDefinition('clock', 'Tells the time.', ['type' => 'object', 'properties' => []])]);

        $stream = $adapter->stream($request, $target);

        foreach ($stream as $_) {
        }

        $result = $stream->result();

        self::assertTrue($result->complete);
        self::assertSame('clock', $result->toolCalls[0]->name);
        self::assertSame('anthropic', $result->providerState['provider']);
        self::assertNotSame('', $result->providerState['thinking'][0]['signature']);
        self::assertSame(729, $result->tokenUsage->inputTokens);
        self::assertSame(AiFinishReason::ToolCalls, $result->finishReason);

        $adapter->complete($request->withMessages(AiMessage::fromResponse($this->responseOf($result)), AiMessage::toolResult($result->toolCalls[0]->id, '12:00')), $target);

        $assistant = $this->lastBody()['messages'][1]['content'];

        self::assertSame('thinking', $assistant[0]['type']);
        self::assertSame($result->providerState['thinking'][0]['signature'], $assistant[0]['signature']);
        self::assertSame('tool_use', $assistant[array_key_last($assistant)]['type']);
    }

    public function testTheStateOfAnotherProviderIsNotSentToAnthropic(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']])));
        $call = new AiToolCall('call_1', 'list_categories', []);
        $result = new AiProviderResult('Let me look.', null, [$call], new AiTokenUsage(1, 1), AiFinishReason::ToolCalls, 'x', null, ['provider' => 'mistral', 'thinking' => [['content' => 'hm', 'signature' => 's']]]);

        $adapter->complete($this->request([AiMessage::user('Which?'), AiMessage::fromResponse($this->responseOf($result)), AiMessage::toolResult('call_1', 'Chairs')]), $this->target(AiProvider::Anthropic));

        self::assertSame(['text', 'tool_use'], array_column($this->lastBody()['messages'][1]['content'], 'type'));
    }

    public function testARequestThatDoesNotAskForThinkingGetsNoneSoItsBudgetGoesToTheAnswer(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport([Wire::anthropic([['type' => 'text', 'text' => 'ok']]), Wire::anthropic([['type' => 'text', 'text' => 'ok']]), Wire::anthropic([['type' => 'text', 'text' => 'ok']])]));
        $target = $this->target(AiProvider::Anthropic);

        $adapter->complete($this->request(), $target);
        self::assertSame(['type' => 'disabled'], $this->lastBody()['thinking']);

        $adapter->complete($this->request(providerOptions: ['anthropic' => ['thinking' => ['type' => 'adaptive']]]), $target);
        self::assertSame(['type' => 'adaptive'], $this->lastBody()['thinking'], 'a request that asks for thinking keeps it');

        $adapter->complete($this->request(providerOptions: ['anthropic' => ['effort' => 'low']]), $target);
        self::assertSame(['type' => 'disabled'], $this->lastBody()['thinking']);
        self::assertSame('low', $this->lastBody()['output_config']['effort']);
    }

    public function testAToolCallCutAtTheLimitKeepsTheTextAndTheTokensInsteadOfFailing(): void
    {
        $cut = Wire::anthropic([['type' => 'text', 'text' => 'Let me look'], ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'list_categories', 'input' => ['limit' => 3]]], 'max_tokens', ['input_tokens' => 40, 'output_tokens' => 600, 'cache_read_input_tokens' => 5, 'cache_creation_input_tokens' => 7]);
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport($cut));

        $result = $adapter->complete($this->request(tools: [new AiToolDefinition('list_categories', 'Lists.', ['type' => 'object', 'properties' => []])], maxOutputTokens: 600), $this->target(AiProvider::Anthropic));

        self::assertSame(AiFinishReason::MaxTokens, $result->finishReason);
        self::assertSame('Let me look', $result->text);
        self::assertSame([], $result->toolCalls);
        self::assertEquals(new AiTokenUsage(40, 600, 5, 7), $result->tokenUsage);
        self::assertSame('claude-probe-2026', $result->model);
    }

    public function testAStreamCutAtTheLimitDeliversItsTextThenSaysMaxTokens(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicStream(25, 600, stop: 'max_tokens')));
        $stream = $adapter->stream($this->request(), $this->target(AiProvider::Anthropic));
        $texts = [];

        foreach ($stream as $event) {
            $texts[] = $event->text;
        }

        $result = $stream->result();

        self::assertSame(['Bon', 'jour'], $texts);
        self::assertSame('Bonjour', $result->text);
        self::assertSame(AiFinishReason::MaxTokens, $result->finishReason);
        self::assertTrue($result->complete);
        self::assertSame([25, 600], [$result->tokenUsage->inputTokens, $result->tokenUsage->outputTokens]);
    }

    public function testAStreamThatSpentItsWholeBudgetThinkingIsAnEmptyMaxTokensAnswerWithItsTokens(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicThinkingOnlyStream()));
        $stream = $adapter->stream($this->request(), $this->target(AiProvider::Anthropic));

        foreach ($stream as $_) {
        }

        $result = $stream->result();

        self::assertSame('', $result->text);
        self::assertSame(AiFinishReason::MaxTokens, $result->finishReason);
        self::assertSame([22, 600], [$result->tokenUsage->inputTokens, $result->tokenUsage->outputTokens]);
    }

    /** @return iterable<string, array{string, AiFinishReason}> */
    public static function stopReasons(): iterable
    {
        yield 'end of turn' => ['end_turn', AiFinishReason::Completed];
        yield 'stop sequence' => ['stop_sequence', AiFinishReason::Completed];
        yield 'output limit' => ['max_tokens', AiFinishReason::MaxTokens];
        yield 'refusal' => ['refusal', AiFinishReason::ContentFilter];
        yield 'a pause of the provider' => ['pause_turn', AiFinishReason::Other];
        yield 'a word that does not exist yet' => ['something_new_in_2027', AiFinishReason::Other];
    }

    #[DataProvider('stopReasons')]
    public function testTheStopReasonIsMappedAndAnUnknownOneIsOther(string $reason, AiFinishReason $expected): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'partial']], $reason)));

        self::assertSame($expected, $adapter->complete($this->request(), $this->target(AiProvider::Anthropic))->finishReason);
    }

    public function testTheDefaultEndpointIsThePublicApiOfAnthropic(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']])), self::PINNED);

        $adapter->complete($this->request(), $this->target(AiProvider::Anthropic, null));

        self::assertSame('https://api.anthropic.com/v1/messages', $this->sent[0]['url']);
        self::assertSame('2023-06-01', $this->sent[0]['headers']['anthropic-version']);
    }

    private function responseOf(AiProviderResult $result): AiResponse
    {
        return new AiResponse($result->text, $result->data, $result->toolCalls, $result->tokenUsage, '0', $result->model, 'probe-connection', $result->finishReason, [], 1, null, 'USD', $result->providerState);
    }

    public function testTheRequestIdIsTheHeaderOfTheProvider(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']], headers: ['request-id' => Wire::ANTHROPIC_REQUEST_ID])));

        self::assertSame(Wire::ANTHROPIC_REQUEST_ID, $adapter->complete($this->request(), $this->target(AiProvider::Anthropic))->requestId);
    }

    public function testWithoutTheHeaderTheRequestIdIsTheIdOfTheMessage(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']])));

        self::assertSame('msg_01', $adapter->complete($this->request(), $this->target(AiProvider::Anthropic))->requestId);
    }

    public function testTheRequestIdOfAStreamIsTheHeaderOfTheProvider(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicStream(headers: ['request-id' => Wire::ANTHROPIC_REQUEST_ID])));
        $stream = $adapter->stream($this->request(), $this->target(AiProvider::Anthropic));

        foreach ($stream as $_) {
        }

        self::assertSame(Wire::ANTHROPIC_REQUEST_ID, $stream->result()->requestId);
    }

    public function testARefusalIsAContentFilterAnswerWithItsCategoryAndItsTokens(): void
    {
        // The documented refusal has an empty content: the library alone fails on it ("Response does not contain any content").
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicRefusal()));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::Anthropic));

        self::assertSame(AiFinishReason::ContentFilter, $result->finishReason);
        self::assertSame('', $result->text);
        self::assertSame('cyber', $result->refusalCategory);
        self::assertSame('This request was declined because it could enable cyber harm.', $result->refusalDetail);
        self::assertSame(Wire::ANTHROPIC_REQUEST_ID, $result->requestId);
        self::assertEquals(new AiTokenUsage(412, 0), $result->tokenUsage);
        self::assertSame('claude-fable-5', $result->model);
    }

    public function testARefusalWithoutCategoryKeepsNullAsTheDocumentationSays(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicRefusal(['type' => 'refusal', 'category' => null, 'explanation' => null])));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::Anthropic));

        self::assertSame(AiFinishReason::ContentFilter, $result->finishReason);
        self::assertNull($result->refusalCategory);
        self::assertNull($result->refusalDetail);
    }

    public function testAnAnswerThatIsNotARefusalCarriesNoRefusalField(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropic([['type' => 'text', 'text' => 'ok']])));

        $result = $adapter->complete($this->request(), $this->target(AiProvider::Anthropic));

        self::assertNull($result->refusalCategory);
        self::assertNull($result->refusalDetail);
    }

    public function testAStreamThatEndsOnARefusalIsAContentFilterAnswer(): void
    {
        $adapter = $this->adapter(AnthropicAdapter::class, $this->transport(Wire::anthropicStream(stop: 'refusal')));
        $stream = $adapter->stream($this->request(), $this->target(AiProvider::Anthropic));

        foreach ($stream as $_) {
        }

        self::assertSame(AiFinishReason::ContentFilter, $stream->result()->finishReason);
    }
}
