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

use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorResolverInterface;
use Thelia\Domain\Ai\AiCapability;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Catalog\AiCostCalculator;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Guardrail\AiGuardrailRunner;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Log\AiLogEntry;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Domain\Ai\Provider\AbstractPlatformAdapter;
use Thelia\Domain\Ai\Provider\AiTarget;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiRateLimiter;
use Thelia\Domain\Ai\Service\AiSettings;

/**
 * Everything the gateway checks before it calls a provider, in this order: the request stays within what its
 * usage declared; the usage is active (it is declared, switched on, and has a budget when public); a connection
 * and a model that can do what the usage needs are assigned; the connection is allowed by the option "EU only"
 * when the merchant turned it on; the input is not longer than the limit; the actor is within its rate; the input
 * guardrails pass; the cost the call can reach fits in the budget of the usage and in the global cap.
 *
 * A refusal after the connection is known is written to the log, with the verdicts so far, status `rejected`
 * (rate, budget, jurisdiction) or `blocked` (a guardrail) and no cost, then thrown. An inactive usage is not
 * logged: the module is meant to hide the feature, not to keep asking.
 *
 * @internal
 */
final readonly class AiAdmission
{
    public function __construct(
        private AiUsageRegistryInterface $usages,
        private AiUsageSettingRepository $settings,
        private AiSettings $globalSettings,
        private AiTargetResolver $targets,
        private AiActorResolverInterface $actors,
        private AiActorHasher $hasher,
        private AiRequestMeasure $measure,
        private AiRateLimiter $rateLimiter,
        private AiGuardrailRunner $guardrails,
        private AiCostCalculator $calculator,
        private AiBudgetLedger $ledger,
        private AiUsageLogWriter $log,
    ) {
    }

    /**
     * @throws \Thelia\Domain\Ai\Exception\UnknownAiUsageException
     * @throws AiUsageViolationException
     * @throws InactiveAiUsageException
     * @throws AiJurisdictionRefusedException
     * @throws AiGuardrailBlockedException
     * @throws AiRateLimitExceededException
     * @throws AiBudgetExceededException
     */
    public function admit(AiRequest $request, AiLogKind $kind): AiAdmittedCall
    {
        $startedAt = hrtime(true);
        $usage = $this->usages->get($request->usageId);
        $this->assertDeclared($request, $usage, $kind);

        if (!$this->usages->isActive($usage->id)) {
            throw new InactiveAiUsageException($usage->id);
        }

        $setting = $this->settings->findByUsage($usage->id);
        $target = $this->targets->resolve($usage->id);
        $this->assertModelCan($usage, $target);

        // Who is asking is looked up here, before the first refusal that has to name them in the log.
        $actor = $request->actor ?? $this->actors->resolve($usage->audience);
        $actorHash = $this->hasher->hash($actor);
        $effective = self::withLimits($request, $request->actor, $this->outputLimit($request, $setting?->getMaxOutputTokens(), $target));
        // A guardrail may call a third party: it gets the real type of the actor and its hash, never the identifier.
        $guarded = self::withLimits($effective, AiActor::pseudonymous($actor->type, $actorHash), $effective->maxOutputTokens);

        /** @param list<GuardrailVerdict> $verdicts */
        $refuse = function (AiUsageLogStatus $status, string $reason, array $verdicts) use ($kind, $usage, $target, $actor, $actorHash, $request, $startedAt): void {
            $this->log->write(new AiLogEntry(
                usageId: $usage->id,
                kind: $kind,
                actorType: $actor->type,
                actorHash: $actorHash,
                status: $status,
                connectionCode: $target->connectionCode,
                provider: $target->provider->value,
                model: $target->model->name,
                jurisdiction: $target->jurisdiction,
                error: $reason,
                latencyMilliseconds: intdiv(hrtime(true) - $startedAt, 1_000_000),
                verdicts: $verdicts,
                correlationId: $request->correlationId,
            ));
        };

        if ($this->globalSettings->isEuropeanUnionOnly() && AiJurisdiction::EuropeanUnion !== $target->jurisdiction) {
            $refuse(AiUsageLogStatus::Rejected, 'jurisdiction', []);

            throw new AiJurisdictionRefusedException($target->connectionCode, $target->jurisdiction);
        }

        $verdicts = [];
        $inputLimit = $setting?->getMaxInputCharacters() ?? $usage->maxInputCharacters;

        if (null !== $inputLimit && $this->measure->lastUserMessageCharacters($request) > $inputLimit) {
            $blocking = GuardrailVerdict::fail(CoreGuardrail::Limits->value, GuardrailStage::Input, 'input_too_long');
            $refuse(AiUsageLogStatus::Blocked, 'input_too_long', [$blocking]);

            throw new AiGuardrailBlockedException($blocking);
        }

        $verdicts[] = GuardrailVerdict::pass(CoreGuardrail::Limits->value, GuardrailStage::Input);

        $retryAfter = $this->rateLimiter->consume($usage->id, $actorHash, $usage->audience, $setting?->getRequestsPerMinute(), $setting?->getRequestsPerDay());

        if (null !== $retryAfter) {
            $refuse(AiUsageLogStatus::Rejected, 'rate_limit', [...$verdicts, GuardrailVerdict::fail(CoreGuardrail::RateLimit->value, GuardrailStage::Input, 'rate_limit')]);

            throw new AiRateLimitExceededException($usage->id, $retryAfter);
        }

        $verdicts[] = GuardrailVerdict::pass(CoreGuardrail::RateLimit->value, GuardrailStage::Input);

        $selection = $this->guardrails->selection($usage, $setting);
        $guardrailVerdicts = $this->guardrails->run(GuardrailStage::Input, $guarded, null, $selection);

        foreach ($guardrailVerdicts as $verdict) {
            if ($verdict->blocks()) {
                $refuse(AiUsageLogStatus::Blocked, (string) $verdict->reason, [...$verdicts, ...$guardrailVerdicts]);

                throw new AiGuardrailBlockedException($verdict);
            }
        }

        $characters = $this->measure->characters($request);

        try {
            $hold = $this->ledger->reserve(
                $usage->id,
                $this->calculator->reservation($target->model, $characters, (int) $effective->maxOutputTokens),
                $setting?->getMonthlyBudget(),
                $this->globalSettings->monthlyCap(),
            );
        } catch (AiBudgetExceededException $exceeded) {
            $refuse(
                AiUsageLogStatus::Rejected,
                $exceeded->globalCap ? 'budget_global' : 'budget',
                [...$verdicts, GuardrailVerdict::fail(CoreGuardrail::Budget->value, GuardrailStage::Input, $exceeded->globalCap ? 'budget_global' : 'budget'), ...$guardrailVerdicts],
            );

            throw $exceeded;
        }

        return new AiAdmittedCall(
            $kind,
            $usage,
            $setting,
            $effective,
            $guarded,
            $target,
            $actor,
            $actorHash,
            [...$verdicts, GuardrailVerdict::pass(CoreGuardrail::Budget->value, GuardrailStage::Input), GuardrailVerdict::pass(CoreGuardrail::UntrustedContent->value, GuardrailStage::Input)],
            $guardrailVerdicts,
            $selection,
            $hold,
            AiCostCalculator::tokensOf($characters),
            $startedAt,
        );
    }

    private function assertDeclared(AiRequest $request, AsAiUsage $usage, AiLogKind $kind): void
    {
        if ([] !== $request->tools && !$usage->requires(AiCapability::Tools)) {
            throw new AiUsageViolationException($usage->id, 'it sends tools and the usage did not declare the capability "tools".');
        }

        if (null !== $request->outputSchema && !$usage->requires(AiCapability::StructuredOutput)) {
            throw new AiUsageViolationException($usage->id, 'it asks for a structured output and the usage did not declare the capability "structured_output".');
        }

        if (AiLogKind::Stream === $kind && !$usage->requires(AiCapability::Streaming)) {
            throw new AiUsageViolationException($usage->id, 'it reads a stream and the usage did not declare the capability "streaming".');
        }
    }

    /**
     * The model of the tier must do everything the usage declared, or the usage cannot run.
     */
    private function assertModelCan(AsAiUsage $usage, AiTarget $target): void
    {
        foreach ($usage->capabilities as $capability) {
            if (!$target->supports($capability)) {
                throw new InactiveAiUsageException($usage->id);
            }
        }
    }

    /**
     * The output limit of the call: the one the request asks for, or the default of the adapter, never above
     * the limit the merchant set on the usage nor the one of the model. The budget reserves for it.
     */
    private function outputLimit(AiRequest $request, ?int $usageLimit, AiTarget $target): int
    {
        $limit = $request->maxOutputTokens ?? AbstractPlatformAdapter::DEFAULT_OUTPUT_TOKENS;

        foreach ([$usageLimit, $target->model->maxOutputTokens] as $ceiling) {
            if (null !== $ceiling && $ceiling >= 1) {
                $limit = min($limit, $ceiling);
            }
        }

        return $limit;
    }

    private static function withLimits(AiRequest $request, ?AiActor $actor, ?int $maximumOutputTokens): AiRequest
    {
        return new AiRequest(
            $request->usageId,
            $request->messages,
            $request->system,
            $request->untrustedContents,
            $request->outputSchema,
            $request->tools,
            $maximumOutputTokens,
            $request->providerOptions,
            $request->correlationId,
            $actor,
        );
    }
}
