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

namespace Thelia\Tests\Support\Ai;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers of the providers as they come on the wire, for the tests of the adapters.
 *
 * SIMULATED, written from the public documentation of each API (Anthropic Messages, Mistral and OpenAI chat
 * completions, OpenAI Responses, Ollama chat), except the three recorded cassettes of symfony/ai in
 * tests/fixtures/ai/*.json, which the tests that use them say.
 */
final class AiProviderFixtures
{
    // --- chat completions (Mistral, Scaleway, OVHcloud, Albert, compatible endpoints) ---

    /**
     * @param list<array<string, mixed>>|null $toolCalls
     * @param array<string, mixed>            $usageExtra
     */
    public static function chat(string $content, ?array $toolCalls = null, int $promptTokens = 21, int $completionTokens = 20, int $cachedTokens = 0, array $usageExtra = [], string $finish = 'stop'): MockResponse
    {
        return self::json([
            'id' => 'abc', 'object' => 'chat.completion', 'created' => 1786820845, 'model' => 'probe-model-2026',
            'usage' => ['prompt_tokens' => $promptTokens, 'total_tokens' => $promptTokens + $completionTokens, 'completion_tokens' => $completionTokens, 'prompt_tokens_details' => ['cached_tokens' => $cachedTokens]] + $usageExtra,
            'choices' => [['index' => 0, 'finish_reason' => null === $toolCalls ? $finish : 'tool_calls', 'message' => ['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls]]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function chatToolCall(string $name = 'list_categories', string $arguments = '{"limit": 3}', string $id = 'call_1'): array
    {
        return [['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => $arguments], 'index' => 0]];
    }

    /**
     * @param list<string> $chunks
     */
    public static function chatStream(array $chunks = ['Bon', 'jour'], int $promptTokens = 10, int $completionTokens = 10, int $cachedTokens = 0): MockResponse
    {
        $chunk = static fn (array $delta, ?string $finish = null, ?array $usage = null): string => 'data: '.json_encode(array_filter([
            'id' => 'cmpl-1', 'object' => 'chat.completion.chunk', 'created' => 1786820845, 'model' => 'probe-model-2026',
            'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]],
            'usage' => $usage,
        ], static fn (mixed $value): bool => null !== $value), \JSON_THROW_ON_ERROR)."\n\n";

        $body = $chunk(['role' => 'assistant', 'content' => '']);

        foreach ($chunks as $index => $text) {
            $last = $index === \count($chunks) - 1;
            $body .= $chunk(['content' => $text], $last ? 'stop' : null, $last ? ['prompt_tokens' => $promptTokens, 'total_tokens' => $promptTokens + $completionTokens, 'completion_tokens' => $completionTokens, 'prompt_tokens_details' => ['cached_tokens' => $cachedTokens]] : null);
        }

        return self::sse($body."data: [DONE]\n\n");
    }

    public static function chatToolStream(string $name = 'list_categories', string $id = 'call_1'): MockResponse
    {
        $chunk = static fn (array $delta, ?string $finish = null, ?array $usage = null): string => 'data: '.json_encode(array_filter([
            'id' => 'cmpl-1', 'object' => 'chat.completion.chunk', 'created' => 1786820845, 'model' => 'probe-model-2026',
            'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]],
            'usage' => $usage,
        ], static fn (mixed $value): bool => null !== $value), \JSON_THROW_ON_ERROR)."\n\n";

        return self::sse(
            $chunk(['role' => 'assistant', 'content' => null, 'tool_calls' => [['index' => 0, 'id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => '']]]])
            .$chunk(['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"limit"']]]])
            .$chunk(['tool_calls' => [['index' => 0, 'function' => ['arguments' => ': 3}']]]])
            .$chunk([], 'tool_calls', ['prompt_tokens' => 30, 'total_tokens' => 42, 'completion_tokens' => 12])
            ."data: [DONE]\n\n",
        );
    }

    // --- Anthropic Messages ---

    /**
     * @param list<array<string, mixed>> $content
     * @param array<string, int>         $usage
     */
    public static function anthropic(array $content, string $stop = 'end_turn', array $usage = ['input_tokens' => 12, 'output_tokens' => 7]): MockResponse
    {
        return self::json(['id' => 'msg_01', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-probe-2026', 'content' => $content, 'stop_reason' => $stop, 'stop_sequence' => null, 'usage' => $usage]);
    }

    public static function anthropicStream(int $inputTokens = 25, int $outputTokens = 15, int $cacheRead = 0, int $cacheWrite = 0): MockResponse
    {
        return self::sse(self::events([
            ['message_start', ['type' => 'message_start', 'message' => ['id' => 'msg_01', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-probe-2026', 'content' => [], 'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => $inputTokens, 'cache_read_input_tokens' => $cacheRead, 'cache_creation_input_tokens' => $cacheWrite, 'output_tokens' => 1]]]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Bon']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'jour']]],
            ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]],
            ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => $outputTokens]]],
            ['message_stop', ['type' => 'message_stop']],
        ]));
    }

    public static function anthropicToolStream(string $name = 'list_categories', string $id = 'toolu_01'): MockResponse
    {
        return self::sse(self::events([
            ['message_start', ['type' => 'message_start', 'message' => ['id' => 'msg_01', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-probe-2026', 'content' => [], 'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => 30, 'output_tokens' => 1]]]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => []]]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"limit"']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => ': 3}']]],
            ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]],
            ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use', 'stop_sequence' => null], 'usage' => ['output_tokens' => 12]]],
            ['message_stop', ['type' => 'message_stop']],
        ]));
    }

    // --- OpenAI Responses ---

    /**
     * @param list<array<string, mixed>> $output
     */
    public static function responses(array $output, int $inputTokens = 120, int $outputTokens = 9, int $cachedTokens = 0, string $status = 'completed'): MockResponse
    {
        return self::json([
            'id' => 'resp_1', 'object' => 'response', 'status' => $status, 'model' => 'gpt-probe-2026',
            'output' => $output,
            'usage' => ['input_tokens' => $inputTokens, 'input_tokens_details' => ['cached_tokens' => $cachedTokens], 'output_tokens' => $outputTokens, 'total_tokens' => $inputTokens + $outputTokens],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function responsesMessage(string $text): array
    {
        return ['type' => 'message', 'id' => 'msg_1', 'status' => 'completed', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function responsesToolCall(string $name = 'list_categories', string $arguments = '{"limit": 3}', string $callId = 'call_1'): array
    {
        return ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => $callId, 'name' => $name, 'arguments' => $arguments, 'status' => 'completed'];
    }

    public static function responsesStream(int $inputTokens = 10, int $outputTokens = 10, int $cachedTokens = 0): MockResponse
    {
        $response = ['id' => 'resp_1', 'object' => 'response', 'status' => 'completed', 'model' => 'gpt-probe-2026', 'output' => [self::responsesMessage('Bonjour')], 'usage' => ['input_tokens' => $inputTokens, 'input_tokens_details' => ['cached_tokens' => $cachedTokens], 'output_tokens' => $outputTokens, 'total_tokens' => $inputTokens + $outputTokens]];

        return self::sse(self::events([
            ['response.created', ['type' => 'response.created', 'response' => ['id' => 'resp_1', 'status' => 'in_progress']]],
            ['response.output_text.delta', ['type' => 'response.output_text.delta', 'item_id' => 'msg_1', 'output_index' => 0, 'content_index' => 0, 'delta' => 'Bon']],
            ['response.output_text.delta', ['type' => 'response.output_text.delta', 'item_id' => 'msg_1', 'output_index' => 0, 'content_index' => 0, 'delta' => 'jour']],
            ['response.completed', ['type' => 'response.completed', 'response' => $response]],
        ]));
    }

    // --- Ollama ---

    /**
     * @param list<array<string, mixed>>|null $toolCalls
     */
    public static function ollama(string $content, ?array $toolCalls = null, int $promptTokens = 26, int $completionTokens = 14): MockResponse
    {
        return self::json(['model' => 'probe:8b', 'created_at' => '2026-10-28T10:00:00Z', 'message' => ['role' => 'assistant', 'content' => $content] + (null === $toolCalls ? [] : ['tool_calls' => $toolCalls]), 'done' => true, 'done_reason' => 'stop', 'prompt_eval_count' => $promptTokens, 'eval_count' => $completionTokens]);
    }

    public static function ollamaStream(int $promptTokens = 26, int $completionTokens = 14): MockResponse
    {
        $lines = [
            ['model' => 'probe:8b', 'message' => ['role' => 'assistant', 'content' => 'Bon'], 'done' => false],
            ['model' => 'probe:8b', 'message' => ['role' => 'assistant', 'content' => 'jour'], 'done' => false],
            ['model' => 'probe:8b', 'message' => ['role' => 'assistant', 'content' => ''], 'done' => true, 'done_reason' => 'stop', 'prompt_eval_count' => $promptTokens, 'eval_count' => $completionTokens],
        ];

        return new MockResponse(implode("\n", array_map(static fn (array $line): string => json_encode($line, \JSON_THROW_ON_ERROR), $lines))."\n", ['http_code' => 200, 'response_headers' => ['content-type' => 'application/x-ndjson']]);
    }

    // --- transport ---

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }

    public static function sse(string $body): MockResponse
    {
        return new MockResponse($body, ['http_code' => 200, 'response_headers' => ['content-type' => 'text/event-stream']]);
    }

    /**
     * A recorded answer of the symfony/ai test suite (tests/fixtures/ai/<name>.json).
     */
    public static function cassette(string $name): MockResponse
    {
        $interaction = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/fixtures/ai/'.$name.'.json'), true, flags: \JSON_THROW_ON_ERROR)['interactions'][0]['response'];

        return new MockResponse($interaction['body'], ['http_code' => $interaction['status'], 'response_headers' => ['content-type' => 'sse' === ($interaction['body_format'] ?? '') ? 'text/event-stream' : 'application/json']]);
    }

    /**
     * @param list<array{0: string, 1: array<string, mixed>}> $events
     */
    private static function events(array $events): string
    {
        $body = '';

        foreach ($events as [$name, $data]) {
            $body .= 'event: '.$name."\ndata: ".json_encode($data, \JSON_THROW_ON_ERROR)."\n\n";
        }

        return $body;
    }
}
