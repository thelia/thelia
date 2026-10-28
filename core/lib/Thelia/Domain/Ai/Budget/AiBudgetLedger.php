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

namespace Thelia\Domain\Ai\Budget;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Domain\Ai\Internal\AiDecimal;
use Thelia\Model\AiBudgetReservation;
use Thelia\Model\AiBudgetReservationQuery;

/**
 * The monthly budget of the usages and the global cap, shared by every server of the shop.
 *
 * What a usage has spent in the calendar month is the sum of its rows in `ai_usage_log`; what is in flight is
 * the sum of its reservations in `ai_budget_reservation` that have not expired. Two calls that each fit alone
 * must not both be let through when together they do not: the sum of the log, the sum of the reservations
 * and the insert of the new reservation therefore run in one transaction that starts by locking a row of the
 * database, the one of the setting `ai_monthly_cap`. The lock is a row lock of InnoDB, held until the
 * transaction ends: it works the same with one server or ten, and a crashed process gives it back with its
 * connection. A lock is taken for every usage, not one per usage, because the global cap is a sum over all of
 * them.
 *
 * The transaction is short (two aggregate reads on indexed columns and one insert). Taken as the outermost one,
 * the lock comes before the first plain read, so those reads see what the previous holder committed; nested in
 * a transaction of the caller that already read these tables, InnoDB may show a picture taken earlier, so call
 * the gateway outside long transactions.
 *
 * A refusal never rolls back: a nested rollback would mark the transaction of the caller as unable to commit.
 * Amounts are added as they are written, in the currency of the prices that produced them, whatever
 * `ai_usage_setting.currency` says: the layer converts nothing.
 *
 * @internal
 */
final readonly class AiBudgetLedger
{
    /** The row every reservation locks first: it always exists (install and update seed it) and is created if a shop deleted it. */
    public const string LOCK_CONFIG_NAME = 'ai_monthly_cap';

    /** Longer than the longest call (a stream times out after five minutes): a crashed call gives its hold back after that. */
    public const int HOLD_LIFETIME_SECONDS = 900;

    /**
     * @param string      $amount      the highest cost of the call, decimal string: '0' only checks that the budget is not reached yet
     * @param string|null $usageBudget the monthly budget of the usage, decimal string; null or zero: the usage has none of its own
     * @param string      $monthlyCap  the global monthly cap, decimal string; '0': no global cap
     *
     * @throws AiBudgetExceededException the budget of the usage, or the global cap, cannot take the amount
     */
    public function reserve(string $usageId, string $amount, ?string $usageBudget, string $monthlyCap): AiBudgetHold
    {
        $amountUnits = AiDecimal::parse($amount);
        $budgetUnits = null === $usageBudget ? 0 : AiDecimal::parse($usageBudget);
        $capUnits = AiDecimal::parse($monthlyCap);

        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);

        $connection->beginTransaction();

        try {
            $outcome = $this->attempt($connection, $usageId, $amountUnits, $budgetUnits, $capUnits);
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        // Thrown once the transaction is over, see the class comment.
        if ($outcome instanceof AiBudgetExceededException) {
            throw $outcome;
        }

        return $outcome;
    }

    /**
     * Gives the hold back. Safe to call twice: a hold that the log write already replaced is not released again.
     */
    public function release(AiBudgetHold $hold, ?ConnectionInterface $connection = null): void
    {
        if ($hold->isReleased()) {
            return;
        }

        AiBudgetReservationQuery::create()->filterById($hold->id)->delete($connection);

        $hold->markReleased();
    }

    /**
     * The two checks and the insert, under the lock. The refusal is returned, not thrown.
     */
    private function attempt(ConnectionInterface $connection, string $usageId, int $amountUnits, int $budgetUnits, int $capUnits): AiBudgetHold|AiBudgetExceededException
    {
        $this->lock($connection);

        $now = new \DateTimeImmutable();
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $this->deleteExpired($connection, $now);

        if ($budgetUnits > 0 && self::exceeds($this->spent($connection, $monthStart, $usageId) + $this->held($connection, $now, $usageId), $amountUnits, $budgetUnits)) {
            return new AiBudgetExceededException($usageId);
        }

        if ($capUnits > 0 && self::exceeds($this->spent($connection, $monthStart) + $this->held($connection, $now), $amountUnits, $capUnits)) {
            return new AiBudgetExceededException($usageId, true);
        }

        $row = (new AiBudgetReservation())
            ->setUsageId($usageId)
            ->setAmount(AiDecimal::format($amountUnits))
            ->setCurrency('USD')
            ->setExpiresAt($now->modify(\sprintf('+%d seconds', self::HOLD_LIFETIME_SECONDS)));
        $row->save($connection);

        return new AiBudgetHold($row->getId(), $usageId, AiDecimal::format($amountUnits));
    }

    /**
     * The amount that no longer fits: what is spent and held, plus this call, is above the limit. A call of
     * no cost is refused too once the limit is reached, so that "reached" means the same for every call.
     */
    private static function exceeds(int $used, int $amount, int $limit): bool
    {
        return $used >= $limit || $used + $amount > $limit;
    }

    private function lock(ConnectionInterface $connection): void
    {
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $statement = $connection->prepare('SELECT `id` FROM `config` WHERE `name` = :name FOR UPDATE');
            $statement->execute([':name' => self::LOCK_CONFIG_NAME]);
            // fetchColumn() of Propel's statement answers an empty string, not false, when no row matches.
            $found = [] !== $statement->fetchAll(\PDO::FETCH_COLUMN);
            $statement->closeCursor();

            if ($found) {
                return;
            }

            $connection->prepare('INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (:name, \'0\', 0, 0, NOW(), NOW())')
                ->execute([':name' => self::LOCK_CONFIG_NAME]);
        }

        throw new \LogicException(\sprintf('The configuration row "%s" that locks the AI budget cannot be created.', self::LOCK_CONFIG_NAME));
    }

    private function deleteExpired(ConnectionInterface $connection, \DateTimeImmutable $now): void
    {
        $connection->prepare('DELETE FROM `ai_budget_reservation` WHERE `expires_at` <= :now')->execute([':now' => $now->format('Y-m-d H:i:s')]);
    }

    private function spent(ConnectionInterface $connection, \DateTimeImmutable $monthStart, ?string $usageId = null): int
    {
        return $this->sum(
            $connection,
            'SELECT COALESCE(SUM(`cost`), 0) FROM `ai_usage_log` WHERE `created_at` >= :from'.(null === $usageId ? '' : ' AND `usage_id` = :usage'),
            [':from' => $monthStart->format('Y-m-d H:i:s')] + (null === $usageId ? [] : [':usage' => $usageId]),
        );
    }

    private function held(ConnectionInterface $connection, \DateTimeImmutable $now, ?string $usageId = null): int
    {
        return $this->sum(
            $connection,
            'SELECT COALESCE(SUM(`amount`), 0) FROM `ai_budget_reservation` WHERE `expires_at` > :now'.(null === $usageId ? '' : ' AND `usage_id` = :usage'),
            [':now' => $now->format('Y-m-d H:i:s')] + (null === $usageId ? [] : [':usage' => $usageId]),
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function sum(ConnectionInterface $connection, string $sql, array $parameters): int
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        $total = $statement->fetchColumn();
        $statement->closeCursor();

        return AiDecimal::parse((string) $total);
    }
}
