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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Ai\Tool\Log\AiToolCallLogWriter;

/**
 * The maintenance of the AI tables: rows of the usage log older than the retention of the shop, prompts whose
 * own expiry has passed (the row stays, only the prompt is emptied) and budget reservations of calls that never
 * finished. A dry run counts and changes nothing.
 *
 * Deletes go by batches so that a year of log does not lock the table in one statement.
 *
 * @internal
 */
final readonly class AiLogPurger
{
    private const int BATCH_SIZE = 2000;

    public function purge(int $retentionDays, bool $dryRun, ?\DateTimeImmutable $now = null): AiPurgeResult
    {
        $now ??= new \DateTimeImmutable();
        $cutoff = $now->modify(\sprintf('-%d days', max(1, $retentionDays)))->format('Y-m-d H:i:s');
        $current = $now->format('Y-m-d H:i:s');
        $argumentsCutoff = $now->modify(\sprintf('-%d days', AiToolCallLogWriter::ARGUMENT_RETENTION_DAYS))->format('Y-m-d H:i:s');
        $connection = Propel::getConnection('TheliaMain');

        $pendingExpired = ' FROM `ai_tool_approval` WHERE `status` = \'pending\' AND `expires_at` < :now';
        $decided = ' FROM `ai_tool_approval` WHERE `created_at` < :cutoff AND `status` NOT IN (\'pending\', \'applying\')';

        // The columns are NOT NULL: a decided proposal keeps its row and loses what the model or the approver typed.
        $emptiable = ' FROM `ai_tool_approval` WHERE `created_at` < :cutoff AND `status` NOT IN (\'pending\', \'applying\') AND (`arguments` <> \'{}\' OR `original_arguments` <> \'{}\')';

        return $dryRun
            ? new AiPurgeResult(
                $this->count($connection, 'SELECT COUNT(*) FROM `ai_usage_log` WHERE `created_at` < :cutoff', [':cutoff' => $cutoff]),
                $this->count($connection, 'SELECT COUNT(*) FROM `ai_usage_log` WHERE `prompt` IS NOT NULL AND `prompt_expires_at` < :now AND `created_at` >= :cutoff', [':now' => $current, ':cutoff' => $cutoff]),
                $this->count($connection, 'SELECT COUNT(*) FROM `ai_budget_reservation` WHERE `expires_at` < :now', [':now' => $current]),
                $this->count($connection, 'SELECT COUNT(*) FROM `ai_tool_call_log` WHERE `created_at` < :cutoff', [':cutoff' => $cutoff]),
                $this->count($connection, 'SELECT COUNT(*) FROM `ai_tool_call_log` WHERE `arguments` IS NOT NULL AND `arguments_expires_at` < :now AND `created_at` >= :cutoff', [':now' => $current, ':cutoff' => $cutoff]),
                $this->count($connection, 'SELECT COUNT(*)'.$pendingExpired, [':now' => $current]),
                $this->count($connection, 'SELECT COUNT(*)'.$decided, [':cutoff' => $cutoff]),
                $this->count($connection, 'SELECT COUNT(*)'.$emptiable, [':cutoff' => $argumentsCutoff]),
            )
            : new AiPurgeResult(
                $this->inBatches($connection, 'DELETE FROM `ai_usage_log` WHERE `created_at` < :cutoff', [':cutoff' => $cutoff]),
                $this->inBatches($connection, 'UPDATE `ai_usage_log` SET `prompt` = NULL WHERE `prompt` IS NOT NULL AND `prompt_expires_at` < :now', [':now' => $current]),
                $this->inBatches($connection, 'DELETE FROM `ai_budget_reservation` WHERE `expires_at` < :now', [':now' => $current]),
                $this->inBatches($connection, 'DELETE FROM `ai_tool_call_log` WHERE `created_at` < :cutoff', [':cutoff' => $cutoff]),
                $this->inBatches($connection, 'UPDATE `ai_tool_call_log` SET `arguments` = NULL WHERE `arguments` IS NOT NULL AND `arguments_expires_at` < :now', [':now' => $current]),
                $this->inBatches($connection, 'UPDATE `ai_tool_approval` SET `status` = \'expired\' WHERE `status` = \'pending\' AND `expires_at` < :now', [':now' => $current]),
                $this->inBatches($connection, 'DELETE'.$decided, [':cutoff' => $cutoff]),
                $this->inBatches($connection, 'UPDATE `ai_tool_approval` SET `arguments` = \'{}\', `original_arguments` = \'{}\' WHERE `created_at` < :cutoff AND `status` NOT IN (\'pending\', \'applying\') AND (`arguments` <> \'{}\' OR `original_arguments` <> \'{}\')', [':cutoff' => $argumentsCutoff]),
            );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function count(ConnectionInterface $connection, string $sql, array $parameters): int
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        $count = (int) $statement->fetchColumn();
        $statement->closeCursor();

        return $count;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function inBatches(ConnectionInterface $connection, string $sql, array $parameters): int
    {
        $statement = $connection->prepare($sql.' LIMIT '.self::BATCH_SIZE);
        $total = 0;

        do {
            $statement->execute($parameters);
            $affected = $statement->rowCount();
            $total += $affected;
        } while (self::BATCH_SIZE === $affected);

        return $total;
    }
}
