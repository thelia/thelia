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

use Thelia\Domain\Ai\AiMessage;
use Thelia\Domain\Ai\AiToolCall;

/**
 * The tools the active modules and the core declared with #[AsAiTool], offered to an actor under its rights.
 *
 * An internal agent builds its request with offer(), runs the calls of the model with answer(), and sends the
 * messages back. Every answer is a JSON object the model can read, even a refusal; nothing here throws for an
 * error of the model.
 */
interface AiToolRegistryInterface
{
    /**
     * The tools this actor may be offered for this usage: those whose audiences include the audience of the usage,
     * that `$only` names (all of them when null), and, for administrators, whose right the actor holds now
     * (for a call through MCP, within the scopes of the token as well). The list is stamped on the context of
     * the offer: a tool outside it is unknown to call() and answer().
     *
     * @param list<string>|null $only the tools the module chooses to give the agent
     *
     * @throws \Thelia\Domain\Ai\Exception\UnknownAiUsageException when the usage of the context is not declared
     */
    public function offer(AiToolContext $context, ?array $only = null): AiToolOffer;

    /**
     * Runs one call. Checks again that the tool is offered and the right still held, then the limits, then the
     * arguments against the schema; a read tool executes, a write tool stores a proposal that waits for approval.
     */
    public function call(AiToolCall $call, AiToolContext $context): AiToolResult;

    /**
     * Answers all the calls of one response of the model, in order: at most 8; each call over the limit still gets
     * its own `limited` result, which the providers require.
     *
     * @param list<AiToolCall> $calls
     *
     * @return list<AiMessage> one tool message per call, for AiRequest::withMessages()
     */
    public function answer(array $calls, AiToolContext $context): array;

    /**
     * @return list<AiToolDescriptor> every declared tool, whoever may use it
     */
    public function describe(): array;
}
