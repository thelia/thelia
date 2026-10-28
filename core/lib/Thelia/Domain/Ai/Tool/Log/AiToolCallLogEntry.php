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

namespace Thelia\Domain\Ai\Tool\Log;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolChannel;

/**
 * One call of a tool, as the journal writes it.
 *
 * @internal
 */
#[Exclude]
final readonly class AiToolCallLogEntry
{
    /**
     * @param string               $effect    read, write or unknown
     * @param array<string, mixed> $arguments what the model wrote: kept 30 days, never put in an error
     */
    public function __construct(
        public string $toolName,
        public string $module,
        public string $effect,
        public AiToolChannel $channel,
        public AiToolCallStatus $status,
        public ?string $error,
        public ?string $usageId,
        public ?string $correlationId,
        public AiActor $actor,
        public ?int $tokenId,
        public ?int $approvalId,
        public array $arguments,
        public ?int $resultBytes,
        public ?int $latencyMilliseconds,
    ) {
    }
}
