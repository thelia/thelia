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

namespace Thelia\Domain\Ai\Tool;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiActorType;

/**
 * A row of ai_tool_approval, as a value: never the Propel model. Everything in it written by a tool or a
 * model (summary, arguments, states) is untrusted text: show it escaped.
 */
#[Exclude]
final readonly class AiToolApprovalView
{
    /**
     * @param array<string, mixed> $originalArguments what the model asked
     * @param array<string, mixed> $arguments         what applies: those of the approver when he amended them
     * @param array<string, mixed> $beforeState
     * @param array<string, mixed> $afterState
     */
    public function __construct(
        public int $id,
        public string $toolName,
        public string $module,
        public ?string $aclResource,
        public ?string $aclModule,
        public string $aclAccess,
        public string $target,
        public string $summary,
        public array $originalArguments,
        public array $arguments,
        public bool $amended,
        public array $beforeState,
        public array $afterState,
        public AiToolApprovalStatus $status,
        public AiToolChannel $channel,
        public ?string $usageId,
        public ?string $correlationId,
        public AiActorType $proposedByType,
        public ?int $proposedByAdminId,
        public ?string $proposedByAdminLogin,
        public ?string $tokenPrefix,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $expiresAt,
        public ?int $decidedByAdminId,
        public ?string $decidedByAdminLogin,
        public ?\DateTimeImmutable $decidedAt,
        public ?string $decisionNote,
        public ?string $error,
    ) {
    }
}
