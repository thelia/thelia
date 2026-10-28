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

namespace Thelia\Domain\Ai;

use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Exception\AiGuardrailBlockedException;
use Thelia\Domain\Ai\Exception\AiJurisdictionRefusedException;
use Thelia\Domain\Ai\Exception\AiProviderUnavailableException;
use Thelia\Domain\Ai\Exception\AiRateLimitExceededException;
use Thelia\Domain\Ai\Exception\AiUsageViolationException;
use Thelia\Domain\Ai\Exception\InactiveAiUsageException;
use Thelia\Domain\Ai\Exception\UnknownAiUsageException;
use Thelia\Domain\Ai\Stream\StreamEvent;

/**
 * The single way to call a model. Before the call: the usage is declared and active, the request stays
 * within what the usage declared, the actor is within its rate, the reserved cost fits in the budget, the
 * connection is allowed by the jurisdiction setting, the input guardrails pass. After: cost computed from
 * the catalogue, budget reconciled, output guardrails, one row in `ai_usage_log`. Every refusal is logged
 * too, with the status `rejected` or `blocked`.
 *
 * The gateway never runs a tool and never retries on its own: a retryable failure is the caller's to retry.
 */
interface AiGatewayInterface
{
    /**
     * @throws UnknownAiUsageException
     * @throws AiUsageViolationException
     * @throws InactiveAiUsageException
     * @throws AiRateLimitExceededException
     * @throws AiBudgetExceededException
     * @throws AiJurisdictionRefusedException
     * @throws AiGuardrailBlockedException
     * @throws AiProviderUnavailableException
     */
    public function complete(AiRequest $request): AiResponse;

    /**
     * The same call, read as it is produced. The checks run when the iteration starts, so the exceptions of
     * complete() are thrown by the first iteration, not by this method. The last event is StreamCompleted.
     *
     * The log row is written when the stream ends, and also when the caller stops iterating before the end
     * (status `aborted`, the tokens known at that moment). Output guardrails run on the whole text at the end.
     *
     * @return iterable<StreamEvent>
     */
    public function stream(AiRequest $request): iterable;
}
