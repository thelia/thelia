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

namespace Thelia\Domain\Ai\Guardrail;

use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\Provider\AiProviderResult;

/**
 * The deterministic guardrails of the answer, applied by the gateway whatever the usage asks for:
 * `output_schema` (the answer decodes and matches the schema of the request) then `html_sanitizer` (the fields
 * the schema marks as HTML are reduced to safe elements).
 *
 * Nothing happens without a schema, and nothing while the model only calls tools: that turn has no answer yet.
 * A complete call that fails the schema is blocked, after the provider was paid. A stream cannot take back what
 * was sent: the failure is reported (`annotate`) and the sanitizing still applies to the final data.
 *
 * @internal
 */
final readonly class AiCoreOutputGuardrails
{
    public function __construct(
        private AiSchemaValidator $validator,
        private AiHtmlSanitizer $sanitizer,
    ) {
    }

    public function check(AiRequest $request, AiProviderResult $result, bool $annotateOnly): AiCoreOutputCheck
    {
        $schema = $request->outputSchema?->schema;

        // A refusal of the provider is not a broken answer: the module reads it from the finish reason.
        if (null === $schema || [] !== $result->toolCalls || AiFinishReason::ContentFilter === $result->finishReason) {
            return new AiCoreOutputCheck($result, []);
        }

        $mode = $annotateOnly ? GuardrailMode::Annotate : GuardrailMode::Block;
        $failure = $this->failure($schema, $result);
        $verdicts = [
            null === $failure
                ? GuardrailVerdict::pass(CoreGuardrail::OutputSchema->value, GuardrailStage::Output)->withMode($mode)
                : GuardrailVerdict::fail(CoreGuardrail::OutputSchema->value, GuardrailStage::Output, $failure)->withMode($mode),
        ];

        // A refused answer is never returned: there is nothing to sanitize.
        if ($verdicts[0]->blocks() || null === $result->data || !$this->sanitizer->hasHtmlFields($schema)) {
            return new AiCoreOutputCheck($result, $verdicts);
        }

        $sanitized = $this->sanitizer->sanitizeData($schema, $result->data);

        if (!\is_array($sanitized)) {
            return new AiCoreOutputCheck($result, $verdicts);
        }

        $changed = $sanitized !== $result->data;
        $verdicts[] = new GuardrailVerdict(CoreGuardrail::HtmlSanitizer->value, GuardrailStage::Output, true, GuardrailMode::Annotate, $changed ? 'sanitized' : null);

        if (!$changed) {
            return new AiCoreOutputCheck($result, $verdicts);
        }

        // The text of a structured answer is its JSON: it must not carry what the data no longer does.
        return new AiCoreOutputCheck(
            new AiProviderResult(
                json_encode($sanitized, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
                $sanitized,
                $result->toolCalls,
                $result->tokenUsage,
                $result->finishReason,
                $result->model,
                $result->announcedCost,
                $result->providerState,
                $result->complete,
                $result->requestId,
                $result->refusalCategory,
                $result->refusalDetail,
            ),
            $verdicts,
        );
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function failure(array $schema, AiProviderResult $result): ?string
    {
        if (null === $result->data) {
            return AiFinishReason::MaxTokens === $result->finishReason ? 'truncated' : 'invalid_json';
        }

        $errors = $this->validator->validate($schema, $result->data);

        return [] === $errors ? null : mb_substr('schema_mismatch: '.$errors[0], 0, 200);
    }
}
