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
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiToolCall;

/**
 * What a provider answered, in the terms of the contract and before the gateway adds the cost, the log
 * row and the verdicts of the guardrails to make an AiResponse of it.
 *
 * @internal
 */
#[Exclude]
final readonly class AiProviderResult
{
    /**
     * @param string                    $text            the text of the answer, empty when the model only called tools; the JSON
     *                                                   text itself when a schema was asked for
     * @param array<string, mixed>|null $data            the JSON object of the answer when a schema was asked for and the answer
     *                                                   is a valid JSON object, null otherwise (an answer that is not JSON is for
     *                                                   the `output_schema` guardrail to refuse)
     * @param list<AiToolCall>          $toolCalls
     * @param string                    $model           the model that answered, as the provider names it (a snapshot of the
     *                                                   model asked for, often)
     * @param string|null               $announcedCost   what the provider says the call cost, decimal string, when it says it
     * @param array<string, mixed>      $providerState   opaque: AiResponse::$providerState
     * @param bool                      $complete        false for the snapshot of a stream that was not read to its end: the
     *                                                   tokens are those known at that moment
     * @param string|null               $requestId       AiResponse::$providerRequestId
     * @param string|null               $refusalCategory AiResponse::$refusalCategory
     * @param string|null               $refusalDetail   AiResponse::$refusalDetail
     */
    public function __construct(
        public string $text,
        public ?array $data,
        public array $toolCalls,
        public AiTokenUsage $tokenUsage,
        public AiFinishReason $finishReason,
        public string $model,
        public ?string $announcedCost = null,
        public array $providerState = [],
        public bool $complete = true,
        public ?string $requestId = null,
        public ?string $refusalCategory = null,
        public ?string $refusalDetail = null,
    ) {
    }
}
