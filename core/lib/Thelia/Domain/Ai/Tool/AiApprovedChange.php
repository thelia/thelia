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
 * The change an administrator approved, as AiWriteToolInterface::apply() receives it.
 */
#[Exclude]
final readonly class AiApprovedChange
{
    /**
     * @param array<string, mixed> $arguments the arguments, as amended by the approver when he did
     * @param array<string, mixed> $before    the state the proposal started from
     * @param array<string, mixed> $after     the state the proposal leads to
     */
    public function __construct(
        public int $approvalId,
        public string $toolName,
        public string $target,
        public array $arguments,
        public array $before,
        public array $after,
        public int $approverAdminId,
        public AiActorType $proposedBy,
    ) {
    }
}
