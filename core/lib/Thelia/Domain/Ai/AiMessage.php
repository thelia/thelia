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

namespace Thelia\Domain\Ai;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * One turn of the conversation. Build it with user(), assistant() or toolResult().
 *
 * The result of a tool is treated as untrusted content by the gateway, whatever the tool: it may carry
 * text a third party wrote (a review, a product description).
 */
#[Exclude]
final readonly class AiMessage
{
    /**
     * @param list<AiToolCall> $toolCalls
     */
    private function __construct(
        public AiMessageRole $role,
        public string $content,
        public array $toolCalls = [],
        public ?string $toolCallId = null,
    ) {
    }

    public static function user(string $content): self
    {
        AiAssert::notBlank($content, 'A user message');

        return new self(AiMessageRole::User, $content);
    }

    /**
     * The previous answer of the model, sent back as it came in the response, tool calls included.
     *
     * @param list<AiToolCall> $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        AiAssert::listOf($toolCalls, AiToolCall::class, 'The tool calls of an assistant message');

        if ('' === trim($content) && [] === $toolCalls) {
            throw new InvalidAiArgumentException('An assistant message holds a text, tool calls, or both.');
        }

        return new self(AiMessageRole::Assistant, $content, $toolCalls);
    }

    public static function fromResponse(AiResponse $response): self
    {
        return self::assistant($response->text, $response->toolCalls);
    }

    /**
     * @param array<mixed>|string $result what the tool returned, encoded as JSON when it is an array
     */
    public static function toolResult(string $toolCallId, array|string $result): self
    {
        AiAssert::notBlank($toolCallId, 'The identifier of a tool call');

        return new self(
            AiMessageRole::Tool,
            \is_array($result) ? json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : $result,
            [],
            $toolCallId,
        );
    }
}
