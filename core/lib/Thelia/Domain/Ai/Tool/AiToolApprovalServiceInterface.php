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

use Thelia\Domain\Ai\Tool\Exception\AiToolApprovalException;

/**
 * The decisions on the changes the write tools proposed. Only an administrator in a session of the back
 * office decides: the approver is read from the security context, never passed in. In a command, without an
 * administrator in the session, or while a tool call runs, every decision is refused.
 */
interface AiToolApprovalServiceInterface
{
    /**
     * The proposals waiting for this administrator: pending, not expired, and whose right he holds now.
     *
     * @return list<AiToolApprovalView>
     */
    public function pendingFor(int $adminId, int $limit = 50): array;

    /**
     * The changes left `applying` (decided, outcome never written) that this administrator may close, oldest first.
     *
     * @return list<AiToolApprovalView>
     */
    public function applyingFor(int $adminId, int $limit = 50): array;

    /**
     * One proposal, in any state, for the administrator who holds the right it asks for; null when it does not exist
     * or when he does not hold the right (the two look the same).
     */
    public function viewFor(int $adminId, int $approvalId): ?AiToolApprovalView;

    /**
     * Applies the change. With `$amendedArguments`, the proposal is rebuilt from them (schema, then propose() in
     * the context of the approver) and the original arguments are kept.
     *
     * @param array<string, mixed>|null $amendedArguments
     *
     * @throws AiToolApprovalException
     * @throws \Thelia\Domain\Ai\Exception\InvalidAiArgumentException when the amended arguments do not fit the schema of the tool
     * @throws Exception\AiToolFailedException                        when the tool refuses the amended arguments
     */
    public function approve(int $approvalId, ?array $amendedArguments = null): AiToolApprovalView;

    /**
     * @throws AiToolApprovalException
     */
    public function reject(int $approvalId, ?string $note = null): AiToolApprovalView;

    /**
     * Closes a change left `applying` (the process died between the decision and its outcome) once an
     * administrator checked the shop. A change is never replayed by itself.
     *
     * @throws AiToolApprovalException
     */
    public function markFailed(int $approvalId, string $note): AiToolApprovalView;
}
