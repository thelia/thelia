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
use Thelia\Domain\Ai\AiMessage;

/**
 * What a tool call gave back, ready for the model: AiToolResult::toMessage() carries `content` as JSON.
 * The gateway treats it as untrusted content, whatever the tool.
 */
#[Exclude]
final readonly class AiToolResult
{
    /**
     * @param array<string, mixed> $content    the JSON object the model reads (see the table of statuses of the contract)
     * @param int|null             $approvalId the approval a write tool created, for `pending_approval`
     * @param int                  $logId      the row of ai_tool_call_log
     */
    public function __construct(
        public AiToolCallStatus $status,
        public array $content,
        public ?int $approvalId,
        public int $logId,
    ) {
    }

    public function toMessage(string $toolCallId): AiMessage
    {
        return AiMessage::toolResult($toolCallId, [] === $this->content ? '{}' : $this->content);
    }
}
