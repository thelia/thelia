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
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Internal\AiAssert;

/**
 * What the gateway returns once the call is logged. Built by the gateway; a module builds one only in a
 * test double.
 */
#[Exclude]
final readonly class AiResponse
{
    /**
     * @param string                    $text              the text of the answer, empty when the model only called tools
     * @param array<string, mixed>|null $data              the structured output, decoded and checked against the schema; null without schema
     * @param list<AiToolCall>          $toolCalls         the tools the model wants run before it goes on
     * @param string                    $cost              what the call cost, decimal string in $currency, like `0.05421000`
     * @param string                    $model             the model that answered, as the provider names it
     * @param string                    $connection        the code of the connection used
     * @param list<GuardrailVerdict>    $guardrailVerdicts every check run, passed or not
     * @param int                       $logId             the row of `ai_usage_log`
     * @param AiProvenance|null         $provenance        set when the usage is generative (AI Act, article 50(2))
     * @param array<string, mixed>      $providerState     opaque, do not read: what the provider needs back on the next turn (signed
     *                                                     thinking blocks, container identifiers). AiMessage::fromResponse() carries it
     * @param string|null               $providerRequestId the identifier the provider gave to the call (its request header, else the id of
     *                                                     the message or completion): what its support asks for; null when it gave none
     * @param string|null               $refusalCategory   the policy area the provider names when it refuses (Anthropic `stop_details.category`,
     *                                                     like `cyber`); null for any other answer or a refusal without category
     * @param string|null               $refusalDetail     what the provider says about the refusal (Anthropic `stop_details.explanation`,
     *                                                     the `refusal` text of OpenAI); provider wording, not translated
     */
    public function __construct(
        public string $text,
        public ?array $data,
        public array $toolCalls,
        public AiTokenUsage $tokenUsage,
        public string $cost,
        public string $model,
        public string $connection,
        public AiFinishReason $finishReason,
        public array $guardrailVerdicts,
        public int $logId,
        public ?AiProvenance $provenance = null,
        public string $currency = 'USD',
        public array $providerState = [],
        public ?string $providerRequestId = null,
        public ?string $refusalCategory = null,
        public ?string $refusalDetail = null,
    ) {
        AiAssert::listOf($toolCalls, AiToolCall::class, 'The tool calls');
        AiAssert::listOf($guardrailVerdicts, GuardrailVerdict::class, 'The guardrail verdicts');
        AiAssert::decimal($cost, 'The cost');
        AiAssert::currency($currency);
        AiAssert::notBlank($model, 'The model of a response');
        AiAssert::notBlank($connection, 'The connection of a response');

        if ($logId < 1) {
            throw new InvalidAiArgumentException('A response points at the row of the usage log the gateway wrote.');
        }

        if (AiFinishReason::ToolCalls === $finishReason && [] === $toolCalls) {
            throw new InvalidAiArgumentException('A response that stops for tool calls holds at least one.');
        }
    }

    public function hasToolCalls(): bool
    {
        return [] !== $this->toolCalls;
    }

    /**
     * The verdicts of the guardrails in annotate mode that failed: what the module may want to show or review.
     *
     * @return list<GuardrailVerdict>
     */
    public function annotations(): array
    {
        return array_values(array_filter($this->guardrailVerdicts, static fn (GuardrailVerdict $verdict): bool => !$verdict->passed));
    }
}
