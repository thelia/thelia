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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A refusal the provider answers with a normal response (HTTP 200) and that the library turns into an
 * exception or into plain text: read from the body so the call ends as AiFinishReason::ContentFilter.
 *
 * @internal
 */
#[Exclude]
final readonly class AiProviderRefusal
{
    public function __construct(
        public ?string $category = null,
        public ?string $detail = null,
    ) {
    }

    /**
     * Anthropic Messages: `stop_reason: "refusal"`, with `stop_details.category` and `stop_details.explanation`, both
     * nullable (https://platform.claude.com/docs/en/build-with-claude/refusals-and-fallback#refusal-response).
     *
     * @param array<string, mixed> $data
     */
    public static function fromAnthropic(array $data): ?self
    {
        if ('refusal' !== ($data['stop_reason'] ?? null)) {
            return null;
        }

        $details = \is_array($data['stop_details'] ?? null) ? $data['stop_details'] : [];

        return new self(self::text($details['category'] ?? null), self::text($details['explanation'] ?? null));
    }

    /**
     * Chat completions: `choices[0].finish_reason: "content_filter"`, or a `message.refusal` text, the field OpenAI documents
     * for a refusal (https://platform.openai.com/docs/guides/structured-outputs#refusals).
     *
     * @param array<string, mixed> $data
     */
    public static function fromChatCompletion(array $data): ?self
    {
        $choice = \is_array($data['choices'][0] ?? null) ? $data['choices'][0] : [];
        $refusal = \is_array($choice['message'] ?? null) ? self::text($choice['message']['refusal'] ?? null) : null;

        if ('content_filter' !== ($choice['finish_reason'] ?? null) && null === $refusal) {
            return null;
        }

        return new self(null, $refusal);
    }

    /**
     * Responses API: an output message whose content part is of type `refusal`, or an incomplete response whose
     * `incomplete_details.reason` is `content_filter`.
     *
     * @param array<string, mixed> $data
     */
    public static function fromResponses(array $data): ?self
    {
        foreach (\is_array($data['output'] ?? null) ? $data['output'] : [] as $item) {
            foreach (\is_array($item) && 'message' === ($item['type'] ?? null) && \is_array($item['content'] ?? null) ? $item['content'] : [] as $part) {
                if (\is_array($part) && 'refusal' === ($part['type'] ?? null)) {
                    return new self(null, self::text($part['refusal'] ?? null));
                }
            }
        }

        if ('incomplete' === ($data['status'] ?? null) && \is_array($data['incomplete_details'] ?? null) && 'content_filter' === ($data['incomplete_details']['reason'] ?? null)) {
            return new self();
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
