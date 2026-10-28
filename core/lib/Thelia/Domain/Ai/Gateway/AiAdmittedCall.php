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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\AsAiUsage;
use Thelia\Domain\Ai\Budget\AiBudgetHold;
use Thelia\Domain\Ai\Guardrail\GuardrailMode;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Domain\Ai\Log\AiLogKind;
use Thelia\Domain\Ai\Provider\AiTarget;
use Thelia\Model\AiUsageSetting;

/**
 * A call that passed every check before the provider: what the rest of the pipeline works with.
 *
 * @internal
 */
#[Exclude]
final readonly class AiAdmittedCall
{
    /**
     * @param AiRequest                    $request            what the adapter sends: the output limit is the effective one
     * @param AiRequest                    $guardedRequest     what a guardrail sees: the same, with the hashed actor in place of the actor
     * @param list<GuardrailVerdict>       $coreVerdicts       the deterministic checks, all passed
     * @param list<GuardrailVerdict>       $guardrailVerdicts  the input verdicts of the optional guardrails (annotations only)
     * @param array<string, GuardrailMode> $guardrailSelection
     */
    public function __construct(
        public AiLogKind $kind,
        public AsAiUsage $usage,
        public ?AiUsageSetting $setting,
        public AiRequest $request,
        public AiRequest $guardedRequest,
        public AiTarget $target,
        public AiActor $actor,
        public string $actorHash,
        public array $coreVerdicts,
        public array $guardrailVerdicts,
        public array $guardrailSelection,
        public AiBudgetHold $hold,
        public int $estimatedInputTokens,
        public int $startedAt,
    ) {
    }

    /**
     * @return list<GuardrailVerdict>
     */
    public function verdicts(): array
    {
        return [...$this->coreVerdicts, ...$this->guardrailVerdicts];
    }

    public function latencyMilliseconds(): int
    {
        return intdiv(hrtime(true) - $this->startedAt, 1_000_000);
    }
}
