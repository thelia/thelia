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

use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiGatewayInterface;
use Thelia\Domain\Ai\AiProvenance;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiResponse;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiTransparency;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Catalog\AiCostCalculator;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Log\AiLogEntry;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiProviderResult;

/**
 * The single way to call a model (AiGatewayInterface): admission (AiAdmission), one call to the adapter of the
 * connection with one retry, the output guardrails, the cost, the log row and the settlement of the budget.
 *
 * Whatever happens after the budget was reserved, the reservation is given back: with the log row that replaces
 * it, or in the `finally` of the call.
 *
 * @internal
 */
final class AiGateway implements AiGatewayInterface
{
    /** How long a stored prompt is kept when the merchant did not say. */
    public const int DEFAULT_PROMPT_RETENTION_DAYS = 30;

    /**
     * @param int $retryDelayMicroseconds the pause before the one retry of a call the provider failed to answer
     */
    public function __construct(
        private readonly AiAdmission $admission,
        private readonly AiProviderAdapters $adapters,
        private readonly AiUsageLogWriter $log,
        private readonly AiBudgetLedger $ledger,
        private readonly AiCostCalculator $calculator,
        private readonly AiGuardrailRunner $guardrails,
        private readonly AiPromptSnapshot $prompts,
        private readonly int $retryDelayMicroseconds = 250_000,
    ) {
    }

    public function complete(AiRequest $request): AiResponse
    {
        $call = $this->admission->admit($request, AiLogKind::Completion);

        try {
            try {
                $result = $this->completeWithOneRetry($call);
            } catch (AiProviderUnavailableException $unavailable) {
                throw $this->failed($call, $unavailable);
            }

            $settlement = $this->settle($call, $result, $result->tokenUsage, annotateOnly: false);

            if (null !== $settlement->blocking) {
                throw new AiGuardrailBlockedException($settlement->blocking, $settlement->logId);
            }

            return $this->response($call, $result, $settlement);
        } finally {
            $this->ledger->release($call->hold);
        }
    }

    public function stream(AiRequest $request): iterable
    {
        throw new \LogicException('Not yet.');
    }

    private function completeWithOneRetry(AiAdmittedCall $call): AiProviderResult
    {
        $adapter = $this->adapters->for($call->target->provider);

        for ($attempt = 0;; ++$attempt) {
            try {
                return $adapter->complete($call->request, $call->target);
            } catch (AiProviderUnavailableException $unavailable) {
                if (!$unavailable->retryable || $attempt >= 1) {
                    throw $unavailable;
                }

                $this->pause();
            }
        }
    }

    private function pause(): void
    {
        if ($this->retryDelayMicroseconds > 0) {
            usleep($this->retryDelayMicroseconds);
        }
    }

    /**
     * Logs a call the provider never answered (status `failed`, no cost) and returns the exception to throw: the
     * same one, pointing at the row.
     */
    private function failed(AiAdmittedCall $call, AiProviderUnavailableException $unavailable): AiProviderUnavailableException
    {
        $logId = $this->log->write($this->entry($call, AiUsageLogStatus::Failed, error: $unavailable->retryable ? 'provider_unavailable_retryable' : 'provider_unavailable', verdicts: $call->verdicts()), $call->hold);

        return new AiProviderUnavailableException($unavailable->connection, $unavailable->retryable, $unavailable->getMessage(), $unavailable->getPrevious(), $logId);
    }

    /**
     * Runs the output guardrails, prices the call and writes its row in place of the reservation.
     */
    private function settle(AiAdmittedCall $call, AiProviderResult $result, AiTokenUsage $tokens, bool $annotateOnly): AiSettlement
    {
        $output = '' !== $result->text ? $result->text : (null === $result->data ? null : json_encode($result->data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        $outputVerdicts = $this->guardrails->run(GuardrailStage::Output, $call->guardedRequest, $output ?? '', $call->guardrailSelection, $annotateOnly);
        $verdicts = [...$call->verdicts(), ...$outputVerdicts];
        $blocking = null;

        foreach ($outputVerdicts as $verdict) {
            if ($verdict->blocks()) {
                $blocking = $verdict;
            }
        }

        $cost = $this->calculator->cost($call->target->model, $tokens, $result->announcedCost);
        $logId = $this->log->write(
            $this->entry(
                $call,
                null === $blocking ? AiUsageLogStatus::Succeeded : AiUsageLogStatus::Blocked,
                cost: $cost,
                tokens: $tokens,
                model: '' !== $result->model ? $result->model : $call->target->model->name,
                finishReason: $result->finishReason,
                error: $blocking?->reason,
                verdicts: $verdicts,
            ),
            $call->hold,
        );

        return new AiSettlement($logId, $cost, $verdicts, $blocking);
    }

    private function response(AiAdmittedCall $call, AiProviderResult $result, AiSettlement $settlement): AiResponse
    {
        $model = '' !== $result->model ? $result->model : $call->target->model->name;
        $marked = AiTransparency::Generative === $call->usage->transparency && ($call->setting?->getProvenanceMark() ?? true);

        return new AiResponse(
            $result->text,
            $result->data,
            $result->toolCalls,
            $result->tokenUsage,
            $settlement->cost,
            $model,
            $call->target->connectionCode,
            $result->finishReason,
            $settlement->verdicts,
            $settlement->logId,
            $marked ? new AiProvenance($call->usage->id, $model, new \DateTimeImmutable(), $settlement->logId) : null,
            'USD',
            $result->providerState,
        );
    }

    /**
     * @param list<GuardrailVerdict> $verdicts
     */
    private function entry(AiAdmittedCall $call, AiUsageLogStatus $status, string $cost = '0', ?AiTokenUsage $tokens = null, ?string $model = null, ?AiFinishReason $finishReason = null, ?string $error = null, array $verdicts = []): AiLogEntry
    {
        $storePrompt = (bool) $call->setting?->getStorePrompt();

        return new AiLogEntry(
            usageId: $call->usage->id,
            kind: $call->kind,
            actorType: $call->actor->type,
            actorHash: $call->actorHash,
            status: $status,
            cost: $cost,
            tokenUsage: $tokens ?? new AiTokenUsage(0, 0),
            connectionCode: $call->target->connectionCode,
            provider: $call->target->provider->value,
            model: $model ?? $call->target->model->name,
            jurisdiction: $call->target->jurisdiction,
            error: $error,
            finishReason: $finishReason,
            latencyMilliseconds: $call->latencyMilliseconds(),
            verdicts: $verdicts,
            correlationId: $call->request->correlationId,
            prompt: $storePrompt ? $this->prompts->encode($call->request) : null,
            promptExpiresAt: $storePrompt ? new \DateTimeImmutable(\sprintf('+%d days', self::promptRetentionDays($call))) : null,
        );
    }

    private static function promptRetentionDays(AiAdmittedCall $call): int
    {
        $days = $call->setting?->getPromptRetentionDays();

        return null !== $days && $days >= 1 ? $days : self::DEFAULT_PROMPT_RETENTION_DAYS;
    }
}
