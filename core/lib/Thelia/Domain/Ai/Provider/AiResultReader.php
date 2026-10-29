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

namespace Thelia\Domain\Ai\Provider;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * The answer of the library as an AiProviderResult.
 *
 * @internal
 */
final readonly class AiResultReader
{
    /**
     * The answer of a call that is not streamed.
     */
    public function fromDeferred(DeferredResult $deferred, AiTarget $target, bool $expectsData, ?string $announcedCost = null, ?string $requestId = null): AiProviderResult
    {
        $result = $deferred->getResult();
        $parts = $result instanceof MultiPartResult ? $result->getContent() : [$result];

        $text = '';
        $toolCalls = [];
        $thinking = [];

        foreach ($parts as $part) {
            if ($part instanceof TextResult) {
                $text .= $part->getContent();
            } elseif ($part instanceof ToolCallResult) {
                foreach ($part->getContent() as $toolCall) {
                    $toolCalls[] = self::toolCall($toolCall, $target->connectionCode);
                }
            } elseif ($part instanceof ThinkingResult) {
                $thinking[] = [$part->getContent(), $part->getSignature()];
            }
        }

        $metadata = $deferred->getMetadata();
        $usage = $metadata->get('token_usage');

        return $this->build(
            $target,
            $text,
            $toolCalls,
            $thinking,
            $usage instanceof TokenUsageInterface ? $usage : null,
            self::reason($metadata->get('finish_reason') ?? $result->getMetadata()->get('finish_reason')),
            $expectsData,
            $announcedCost,
            true,
            $requestId,
        );
    }

    /**
     * The answer of a call that is not streamed when the library refused it because the provider cut it at the
     * limit of output tokens (a tool call cut in two): the text of the answer is read from the body of the
     * response, the tool call is dropped, and the reason is MaxTokens.
     */
    public function fromTruncated(DeferredResult $deferred, AiTarget $target, bool $expectsData, ?string $announcedCost = null, ?string $requestId = null): AiProviderResult
    {
        $raw = $deferred->getRawResult();

        try {
            $data = $raw->getData();
        } catch (\Throwable) {
            $data = [];
        }

        $usage = $deferred->getResultConverter()->getTokenUsageExtractor()?->extract($raw, []);

        return $this->build($target, self::textOf($data), [], [], $usage, AiFinishReason::MaxTokens, $expectsData, $announcedCost, true, $requestId);
    }

    /**
     * The answer of a call that is not streamed and that the provider refused (HTTP 200): no text, the tokens it counted,
     * the reason ContentFilter, and the category and the detail it gives. What the model wrote before refusing is dropped:
     * Anthropic says to treat it as incomplete.
     */
    public function fromRefusal(DeferredResult $deferred, AiTarget $target, AiProviderRefusal $refusal, ?string $requestId = null, ?string $announcedCost = null): AiProviderResult
    {
        $raw = $deferred->getRawResult();

        try {
            $usage = $deferred->getResultConverter()->getTokenUsageExtractor()?->extract($raw, []);
        } catch (\Throwable) {
            $usage = null;
        }

        $result = $this->build($target, '', [], [], $usage, AiFinishReason::ContentFilter, false, $announcedCost, true, $requestId);

        return new AiProviderResult($result->text, null, [], $result->tokenUsage, $result->finishReason, $result->model, $announcedCost, [], true, $requestId, $refusal->category, $refusal->detail);
    }

    /**
     * What a stream has delivered, once it was read to its end or at the moment the caller stopped.
     */
    public function fromStream(AiStreamProgress $progress): AiProviderResult
    {
        $metadata = $progress->stream?->getMetadata();
        $usage = $metadata?->get('token_usage');
        $thinking = [];

        // The assistant turn is rebuilt from the deltas: asking for it on a stream that was not read to its end would read the rest.
        if ($progress->complete && null !== $progress->stream) {
            foreach ($progress->stream->getAssistantMessage()->getThinking() as $block) {
                $thinking[] = [$block->getContent(), $block->getSignature()];
            }
        }

        return $this->build(
            $progress->target,
            $progress->text,
            $progress->toolCalls,
            $thinking,
            $usage instanceof TokenUsageInterface ? $usage : null,
            $progress->truncated ? AiFinishReason::MaxTokens : self::reason($metadata?->get('finish_reason')),
            $progress->expectsData,
            null,
            $progress->complete,
            $progress->requestId,
        );
    }

    /**
     * @throws AiProviderUnavailableException when the model names a tool in a way the layer cannot carry
     */
    public static function toolCall(ToolCall $toolCall, string $connection): AiToolCall
    {
        try {
            return new AiToolCall($toolCall->getId(), $toolCall->getName(), $toolCall->getArguments());
        } catch (InvalidAiArgumentException $invalid) {
            throw new AiProviderUnavailableException($connection, true, \sprintf('The model called a tool in a way that cannot be used: %s', $invalid->getMessage()), $invalid);
        }
    }

    /**
     * @param list<AiToolCall>                       $toolCalls
     * @param list<array{0: string, 1: string|null}> $thinking  content and signature of the thinking blocks
     */
    private function build(AiTarget $target, string $text, array $toolCalls, array $thinking, ?TokenUsageInterface $usage, AiFinishReason $reason, bool $expectsData, ?string $announcedCost, bool $complete, ?string $requestId = null): AiProviderResult
    {
        if ([] !== $toolCalls) {
            $reason = AiFinishReason::ToolCalls;
        } elseif (AiFinishReason::ToolCalls === $reason) {
            $reason = AiFinishReason::Other;
        }

        return new AiProviderResult(
            $text,
            $expectsData && [] === $toolCalls ? self::decode($text) : null,
            $toolCalls,
            self::tokens($usage, $target->provider),
            $reason,
            $usage?->getModel() ?? $target->model->name,
            $announcedCost,
            self::providerState($target->provider, $thinking),
            $complete,
            $requestId,
        );
    }

    /**
     * Input tokens at the full rate, output tokens, and the tokens read from or written to the cache of the
     * provider. Anthropic counts the cached tokens apart from its input tokens; the others count them inside.
     */
    public static function tokens(?TokenUsageInterface $usage, AiProvider $provider): AiTokenUsage
    {
        if (null === $usage) {
            return new AiTokenUsage(0, 0);
        }

        $prompt = $usage->getPromptTokens() ?? 0;
        $output = $usage->getCompletionTokens() ?? 0;
        $written = $usage->getCacheCreationTokens() ?? 0;

        if (AiProvider::Anthropic === $provider) {
            return new AiTokenUsage($prompt, $output, $usage->getCacheReadTokens() ?? 0, $written);
        }

        $read = $usage->getCacheReadTokens() ?? $usage->getCachedTokens() ?? 0;

        return new AiTokenUsage(max(0, $prompt - $read - $written), $output, $read, $written);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $text): ?array
    {
        $json = trim($text);

        // A model without native structured output often wraps its JSON in a Markdown fence.
        if (1 === preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $json, $fence)) {
            $json = $fence[1];
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * The text of a response body: the text blocks of Anthropic, the messages of the Responses API, the message of the first choice of the others.
     *
     * @param array<string, mixed> $data
     */
    private static function textOf(array $data): string
    {
        $text = '';

        foreach (\is_array($data['content'] ?? null) ? $data['content'] : [] as $block) {
            if (\is_array($block) && 'text' === ($block['type'] ?? null) && \is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        // The Responses API of OpenAI: output items of type message, whose parts are output_text.
        foreach (\is_array($data['output'] ?? null) ? $data['output'] : [] as $item) {
            foreach (\is_array($item) && 'message' === ($item['type'] ?? null) && \is_array($item['content'] ?? null) ? $item['content'] : [] as $part) {
                if (\is_array($part) && 'output_text' === ($part['type'] ?? null) && \is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        return \is_string($content) ? $content : $text;
    }

    private static function reason(mixed $reason): AiFinishReason
    {
        if (!$reason instanceof FinishReason) {
            return AiFinishReason::Other;
        }

        return match ($reason->getCase()) {
            FinishReasonCase::STOP, FinishReasonCase::STOP_SEQUENCE => AiFinishReason::Completed,
            FinishReasonCase::LENGTH => AiFinishReason::MaxTokens,
            FinishReasonCase::TOOL_CALL => AiFinishReason::ToolCalls,
            FinishReasonCase::CONTENT_FILTER => AiFinishReason::ContentFilter,
            default => self::byWord($reason->getRaw()),
        };
    }

    /**
     * A stop reason the library does not map: the ones that mean the answer stopped short are a truncation.
     * Anthropic `model_context_window_exceeded` (the response filled the context window, to be treated as
     * truncated: https://platform.claude.com/docs/en/build-with-claude/handling-stop-reasons).
     */
    private static function byWord(?string $word): AiFinishReason
    {
        return 'model_context_window_exceeded' === $word ? AiFinishReason::MaxTokens : AiFinishReason::fromProvider($word);
    }

    /**
     * Only the thinking blocks that carry a signature are kept: the provider checks the signature when the
     * block comes back, and refuses a block that has none.
     *
     * @param list<array{0: string|null, 1: string|null}> $thinking
     *
     * @return array<string, mixed>
     */
    private static function providerState(AiProvider $provider, array $thinking): array
    {
        $blocks = [];

        foreach ($thinking as [$content, $signature]) {
            if (null !== $signature && '' !== $signature) {
                $blocks[] = ['content' => (string) $content, 'signature' => $signature];
            }
        }

        return [] === $blocks ? [] : ['provider' => $provider->value, 'thinking' => $blocks];
    }
}
