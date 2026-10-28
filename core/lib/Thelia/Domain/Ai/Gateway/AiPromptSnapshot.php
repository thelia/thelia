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

namespace Thelia\Domain\Ai\Gateway;

use Thelia\Domain\Ai\AiRequest;

/**
 * The request as the log stores it when the merchant asked to keep prompts: the instructions, the conversation
 * and the untrusted data, as sent. The actor, the options of the provider and the key are not part of it.
 *
 * @internal
 */
final readonly class AiPromptSnapshot
{
    public function encode(AiRequest $request): string
    {
        return (string) json_encode([
            'system' => $request->system,
            'messages' => array_map(static fn ($message): array => array_filter([
                'role' => $message->role->value,
                'content' => $message->content,
                'tool_calls' => array_map(static fn ($call): array => ['id' => $call->id, 'name' => $call->name, 'arguments' => $call->arguments], $message->toolCalls),
                'tool_call_id' => $message->toolCallId,
            ], static fn (mixed $value): bool => null !== $value && [] !== $value), $request->messages),
            'untrusted_contents' => array_map(static fn ($content): array => ['source' => $content->source->value, 'reference' => $content->reference, 'content' => $content->content], $request->untrustedContents),
            'tools' => array_map(static fn ($tool): string => $tool->name, $request->tools),
            'output_schema' => $request->outputSchema?->name,
        ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
