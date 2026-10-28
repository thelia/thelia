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

namespace Thelia\Domain\Ai\Log;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiActorType;
use Thelia\Domain\Ai\AiFinishReason;
use Thelia\Domain\Ai\AiJurisdiction;
use Thelia\Domain\Ai\AiTokenUsage;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;

/**
 * One row of `ai_usage_log`, before it is written. The key of a connection is never part of it.
 *
 * @internal
 */
#[Exclude]
final readonly class AiLogEntry
{
    /**
     * @param string                 $cost     decimal string with up to 8 decimals
     * @param list<GuardrailVerdict> $verdicts
     * @param string|null            $prompt   the request as sent, set only when the usage stores prompts
     */
    public function __construct(
        public string $usageId,
        public AiLogKind $kind,
        public AiActorType $actorType,
        public string $actorHash,
        public AiUsageLogStatus $status,
        public string $cost = '0',
        public AiTokenUsage $tokenUsage = new AiTokenUsage(0, 0),
        public ?string $connectionCode = null,
        public ?string $provider = null,
        public ?string $model = null,
        public ?AiJurisdiction $jurisdiction = null,
        public ?string $error = null,
        public ?AiFinishReason $finishReason = null,
        public ?int $latencyMilliseconds = null,
        public array $verdicts = [],
        public ?string $correlationId = null,
        public ?string $prompt = null,
        public ?\DateTimeImmutable $promptExpiresAt = null,
        public string $currency = 'USD',
        public ?string $providerRequestId = null,
    ) {
    }
}
