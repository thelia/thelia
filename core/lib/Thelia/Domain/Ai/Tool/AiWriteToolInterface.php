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

/**
 * A tool that changes the shop. Declare it with #[AsAiTool].
 *
 * A write never takes effect when the model asks: propose() describes the change, the registry stores it as
 * an approval, and apply() runs only when an administrator approves it in the back office. Apply nothing, and
 * send nothing, from propose().
 *
 * apply() runs in a transaction of its own. Whatever leaves the database (an e-mail, a message to a channel,
 * a webhook) must wait until that transaction is committed: dispatch it from a post-commit listener or send a
 * message to the bus, never from inside apply(), where a later failure would roll back the change that was
 * already announced.
 */
interface AiWriteToolInterface
{
    /**
     * @return array<string, mixed> see AiReadToolInterface::inputSchema()
     */
    public function inputSchema(): array;

    /**
     * Describes the change for the administrator, with no effect. It runs again, in the context of the
     * administrator, when he amends the arguments before approving.
     *
     * @param array<string, mixed> $arguments valid against inputSchema()
     *
     * @throws Exception\AiToolFailedException when the change cannot be proposed: the message goes back to the model
     */
    public function propose(array $arguments, AiToolContext $context): AiChangeProposal;

    /**
     * Applies the change an administrator approved. Called by the approval service only.
     *
     * @throws Exception\AiStaleChangeException when the shop no longer matches `$change->before`
     */
    public function apply(AiApprovedChange $change): void;
}
