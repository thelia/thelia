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

use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Ai\Budget\AiBudgetHold;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Guardrail\GuardrailVerdict;
use Thelia\Model\AiUsageLog;

/**
 * Writes the rows of `ai_usage_log`: one per call, refusals included.
 *
 * The row that replaces a reservation is written in the transaction that deletes it, so the budget never
 * counts a call twice (hold and row) nor not at all (neither) between the two statements.
 *
 * @internal
 */
final readonly class AiUsageLogWriter
{
    private const int ERROR_LENGTH = 255;

    private const int REQUEST_ID_LENGTH = 128;

    public function __construct(private AiBudgetLedger $ledger)
    {
    }

    /**
     * @param AiBudgetHold|null $replaces the hold this row settles, released with it
     *
     * @return int the identifier of the row
     */
    public function write(AiLogEntry $entry, ?AiBudgetHold $replaces = null): int
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $row = (new AiUsageLog())
                ->setUsageId($entry->usageId)
                ->setModule(explode('.', $entry->usageId, 2)[0])
                ->setKind($entry->kind->value)
                ->setConnectionCode($entry->connectionCode)
                ->setProvider($entry->provider)
                ->setJurisdiction($entry->jurisdiction?->value)
                ->setModel($entry->model)
                ->setInputTokens($entry->tokenUsage->inputTokens)
                ->setOutputTokens($entry->tokenUsage->outputTokens)
                ->setCacheReadTokens($entry->tokenUsage->cacheReadTokens)
                ->setCacheWriteTokens($entry->tokenUsage->cacheWriteTokens)
                ->setCost($entry->cost)
                ->setCurrency($entry->currency)
                ->setActorType($entry->actorType->value)
                ->setActorHash($entry->actorHash)
                ->setStatus($entry->status->value)
                ->setError(null === $entry->error ? null : mb_strimwidth($entry->error, 0, self::ERROR_LENGTH))
                ->setFinishReason($entry->finishReason?->value)
                ->setProviderRequestId(null === $entry->providerRequestId ? null : mb_strimwidth($entry->providerRequestId, 0, self::REQUEST_ID_LENGTH))
                ->setLatencyMs($entry->latencyMilliseconds)
                ->setGuardrailVerdicts([] === $entry->verdicts ? null : json_encode(array_map(static fn (GuardrailVerdict $verdict): array => $verdict->toArray(), $entry->verdicts), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))
                ->setCorrelationId($entry->correlationId)
                ->setPrompt($entry->prompt)
                ->setPromptExpiresAt($entry->promptExpiresAt);
            $row->save($connection);

            if (null !== $replaces) {
                $this->ledger->release($replaces, $connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return $row->getId();
    }
}
