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

use Thelia\Domain\Ai\AiActorResolverInterface;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\AiCostRecorderInterface;
use Thelia\Domain\Ai\AiExternalCost;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Domain\Ai\Budget\AiBudgetHold;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Guardrail\CoreGuardrail;
use Thelia\Domain\Ai\Guardrail\GuardrailStage;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Internal\AiAssert;
use Thelia\Domain\Ai\Internal\AiDecimal;
use Thelia\Domain\Ai\Log\AiLogEntry;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Domain\Ai\Log\AiUsageLogWriter;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiActorHasher;
use Thelia\Domain\Ai\Service\AiSettings;

/**
 * The way a module accounts for a service it pays outside the gateway (AiCostRecorderInterface).
 *
 * authorize() asks what the gateway asks before a call: the usage is active, the option "EU only" (when turned on)
 * allows the service, the budget of the usage and the global cap take the highest cost of the call; it holds that
 * cost in the budget. record() writes the row and gives the hold back. The two calls of one operation happen in
 * the same process, so the hold is kept here, by usage and service, and taken back in the order it was made; a hold
 * whose record() never comes expires by itself (AiBudgetLedger::HOLD_LIFETIME_SECONDS).
 *
 * Amounts are added as they are written, whatever their currency: the layer converts nothing.
 *
 * @internal
 */
final class AiExternalCostRecorder implements AiCostRecorderInterface
{
    private const int OPERATION_LENGTH = 128;

    /** @var array<string, list<array{AiBudgetHold, AiJurisdiction}>> */
    private array $pending = [];

    public function __construct(
        private readonly AiUsageRegistryInterface $usages,
        private readonly AiUsageSettingRepository $settings,
        private readonly AiSettings $globalSettings,
        private readonly AiBudgetLedger $ledger,
        private readonly AiUsageLogWriter $log,
        private readonly AiActorResolverInterface $actors,
        private readonly AiActorHasher $hasher,
    ) {
    }

    public function authorize(string $usageId, string $provider, AiJurisdiction $jurisdiction, string $maximumCost = '0'): void
    {
        $usage = $this->usages->get($usageId);
        AiExternalCost::assertProvider($provider);
        AiAssert::decimal($maximumCost, 'The maximum cost');

        if (!$this->usages->isActive($usage->id)) {
            throw new InactiveAiUsageException($usage->id);
        }

        if ($this->globalSettings->isEuropeanUnionOnly() && AiJurisdiction::EuropeanUnion !== $jurisdiction) {
            $this->refuse($usage->id, $usage->audience, $provider, $jurisdiction, 'jurisdiction', []);

            throw new AiJurisdictionRefusedException($provider, $jurisdiction);
        }

        try {
            $hold = $this->ledger->reserve($usage->id, $maximumCost, $this->settings->findByUsage($usage->id)?->getMonthlyBudget(), $this->globalSettings->monthlyCap());
        } catch (AiBudgetExceededException $exceeded) {
            $reason = $exceeded->globalCap ? 'budget_global' : 'budget';
            $this->refuse($usage->id, $usage->audience, $provider, $jurisdiction, $reason, [GuardrailVerdict::fail(CoreGuardrail::Budget->value, GuardrailStage::Input, $reason)]);

            throw $exceeded;
        }

        $this->pending[self::key($usage->id, $provider)][] = [$hold, $jurisdiction];
    }

    public function record(AiExternalCost $cost): int
    {
        $usage = $this->usages->get($cost->usageId);

        $actor = $cost->actor ?? $this->actors->resolve($usage->audience);
        $key = self::key($cost->usageId, $cost->provider);
        $held = isset($this->pending[$key]) ? array_shift($this->pending[$key]) : null;

        if ([] === ($this->pending[$key] ?? [])) {
            unset($this->pending[$key]);
        }

        return $this->log->write(
            new AiLogEntry(
                usageId: $cost->usageId,
                kind: AiLogKind::External,
                actorType: $actor->type,
                actorHash: $this->hasher->hash($actor),
                status: $cost->status,
                cost: AiDecimal::format(AiDecimal::parse($cost->cost)),
                tokenUsage: $cost->tokenUsage ?? new AiTokenUsage(0, 0),
                provider: $cost->provider,
                model: mb_strimwidth($cost->operation, 0, self::OPERATION_LENGTH),
                jurisdiction: $held[1] ?? null,
                latencyMilliseconds: $cost->latencyMilliseconds,
                correlationId: $cost->correlationId,
                currency: $cost->currency,
            ),
            $held[0] ?? null,
        );
    }

    /**
     * @param list<GuardrailVerdict> $verdicts
     */
    private function refuse(string $usageId, AiAudience $audience, string $provider, AiJurisdiction $jurisdiction, string $reason, array $verdicts): void
    {
        $actor = $this->actors->resolve($audience);

        $this->log->write(new AiLogEntry(
            usageId: $usageId,
            kind: AiLogKind::External,
            actorType: $actor->type,
            actorHash: $this->hasher->hash($actor),
            status: AiUsageLogStatus::Rejected,
            provider: $provider,
            jurisdiction: $jurisdiction,
            error: $reason,
            verdicts: $verdicts,
        ));
    }

    private static function key(string $usageId, string $provider): string
    {
        return $usageId.'|'.$provider;
    }
}
