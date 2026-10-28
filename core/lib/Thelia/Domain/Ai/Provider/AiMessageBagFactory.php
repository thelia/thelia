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

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiToolCall;

/**
 * The conversation of an AiRequest as the library wants it.
 *
 * What a third party wrote (the untrusted contents of the request and the result of every tool) is wrapped in
 * tags and the system prompt gets the fixed rule that says such text is data. The untrusted contents lead the
 * first user message: they stay the same from one turn of a tool loop to the next, which keeps the start of the
 * prompt identical for the cache of the provider.
 *
 * @internal
 */
final readonly class AiMessageBagFactory
{
    public const string TOOL_RESULT_SOURCE = 'tool_result';

    public function __construct(private UntrustedContentDelimiter $delimiter)
    {
    }

    public function create(AiRequest $request, AiProvider $provider): MessageBag
    {
        $hasTaggedContent = [] !== $request->untrustedContents || $this->hasToolResult($request);
        $system = $this->delimiter->system($request->system, $hasTaggedContent);

        $messages = null === $system ? [] : [Message::forSystem($system)];

        $untrusted = [] === $request->untrustedContents ? null : $this->delimiter->wrapAll($request->untrustedContents);
        $toolNames = [];

        foreach ($request->messages as $message) {
            foreach ($message->toolCalls as $toolCall) {
                $toolNames[$toolCall->id] = $toolCall;
            }

            if (null !== $untrusted && AiMessageRole::User === $message->role) {
                $messages[] = Message::ofUser($untrusted."\n\n".$message->content);
                $untrusted = null;

                continue;
            }

            // The conversation does not start with a user message: the contents get one of their own.
            if (null !== $untrusted) {
                $messages[] = Message::ofUser($untrusted);
                $untrusted = null;
            }

            $messages[] = $this->translate($message, $provider, $toolNames);
        }

        return new MessageBag(...$messages);
    }

    private function hasToolResult(AiRequest $request): bool
    {
        foreach ($request->messages as $message) {
            if (AiMessageRole::Tool === $message->role) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, AiToolCall> $toolCalls the calls of the assistant messages met so far, by identifier
     */
    private function translate(AiMessage $message, AiProvider $provider, array $toolCalls): MessageInterface
    {
        return match ($message->role) {
            AiMessageRole::User => Message::ofUser($message->content),
            AiMessageRole::Assistant => $this->assistant($message, $provider),
            AiMessageRole::Tool => $this->toolResult($message, $toolCalls),
        };
    }

    private function assistant(AiMessage $message, AiProvider $provider): AssistantMessage
    {
        $parts = [];

        // The state came from a provider: another one cannot read it, and a thinking block without its signature is refused.
        if ($provider->value === ($message->providerState['provider'] ?? null)) {
            foreach ($message->providerState['thinking'] ?? [] as $block) {
                if (\is_array($block) && \is_string($block['content'] ?? null) && \is_string($block['signature'] ?? null)) {
                    $parts[] = new Thinking($block['content'], $block['signature']);
                }
            }
        }

        if ('' !== trim($message->content)) {
            $parts[] = new Text($message->content);
        }

        foreach ($message->toolCalls as $toolCall) {
            $parts[] = new ToolCall($toolCall->id, $toolCall->name, $toolCall->arguments);
        }

        /* @var list<ContentInterface> $parts */
        return new AssistantMessage(...$parts);
    }

    /**
     * @param array<string, AiToolCall> $toolCalls
     */
    private function toolResult(AiMessage $message, array $toolCalls): MessageInterface
    {
        $identifier = (string) $message->toolCallId;
        $call = $toolCalls[$identifier] ?? null;

        return Message::ofToolCall(
            new ToolCall($identifier, $call->name ?? 'tool', $call->arguments ?? []),
            $this->delimiter->wrap(self::TOOL_RESULT_SOURCE, $identifier, $call?->name, $message->content),
        );
    }
}
