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

use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\AiRequest;

/**
 * How big a request is, in characters: what the budget reserves for and what the limit on a person's input
 * checks.
 *
 * @internal
 */
final readonly class AiRequestMeasure
{
    /**
     * Everything the provider reads: instructions, conversation, untrusted data, tool descriptions and schema.
     */
    public function characters(AiRequest $request): int
    {
        $total = mb_strlen((string) $request->system);

        foreach ($request->messages as $message) {
            $total += mb_strlen($message->content);

            foreach ($message->toolCalls as $toolCall) {
                $total += mb_strlen($toolCall->name) + mb_strlen(self::json($toolCall->arguments));
            }
        }

        foreach ($request->untrustedContents as $content) {
            $total += mb_strlen($content->content) + mb_strlen((string) $content->reference);
        }

        foreach ($request->tools as $tool) {
            $total += mb_strlen($tool->name) + mb_strlen($tool->description) + mb_strlen(self::json($tool->inputSchema));
        }

        if (null !== $request->outputSchema) {
            $total += mb_strlen(self::json($request->outputSchema->schema));
        }

        return $total;
    }

    /**
     * What a person typed: the last message of the user. A tool result is not typed by anyone.
     */
    public function lastUserMessageCharacters(AiRequest $request): int
    {
        foreach (array_reverse($request->messages) as $message) {
            if (AiMessageRole::User === $message->role) {
                return mb_strlen($message->content);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function json(array $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }
}
