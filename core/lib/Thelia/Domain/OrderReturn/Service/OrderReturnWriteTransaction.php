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

namespace Thelia\Domain\OrderReturn\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderTableMap;

/**
 * The transaction a return is written in: every path that opens a return or
 * changes a returned quantity runs its check and its write through here, and
 * names up front the order and the order product lines it is about.
 *
 * Locks first. The rows are locked before the transaction reads anything,
 * always in the same order: the `order` row, then the `order_product` rows by
 * increasing id. Under REPEATABLE READ, InnoDB takes the picture every plain
 * read of a transaction returns at its first plain read, not at its start; a
 * `SELECT ... FOR UPDATE` does not take it. Locking first therefore means the
 * picture is taken once the locks are held, after the commit of any request
 * that held them: the plain reads that follow see every return written on
 * these lines and this order, and a request that waited cannot be granted the
 * units, or the postage, the one before it took. The locks are on primary
 * keys, so they hold those rows and nothing around them, and one order for
 * every path means two requests cannot each hold what the other waits for.
 *
 * Nested in a transaction someone else opened, the picture may already be
 * taken: the rows are still locked, in the same order, but readsSeeCommits()
 * says no, and ReturnEligibilityChecker falls back to locking reads. Those lock
 * index gaps and may deadlock, or on MariaDB 11.6 and later run into a row
 * changed since the picture; both come back as ReturnRequestConflictException,
 * a refusal the caller can ask to retry, never a unit granted twice.
 */
#[AsAlias(OrderReturnWriteTransactionInterface::class)]
final class OrderReturnWriteTransaction implements OrderReturnWriteTransactionInterface
{
    private const int ER_CHECKREAD = 1020;
    private const int ER_LOCK_DEADLOCK = 1213;

    private bool $readsSeeCommits = false;

    /**
     * What the outermost run() in progress holds: the order id, and its lines
     * by id.
     *
     * @var array{int, array<int, true>}|null
     */
    private ?array $held = null;

    #[\Override]
    public function run(int $orderId, array $orderProductIds, callable $work): mixed
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $outermost = $this->isOutsideAnyTransaction($connection);

        // Outermost, the lines of the order are read before the transaction
        // opens, so that read is not the one that fixes its picture. Nested,
        // the caller's picture may predate the lines: they are all locked, and
        // the eligibility check refuses those of another order.
        $orderProductIds = $outermost
            ? $this->linesOfOrder($orderId, $orderProductIds)
            : $this->distinctIds($orderProductIds);

        $connection->beginTransaction();

        if (!$connection->inTransaction()) {
            throw new \LogicException('The return transaction did not start: the checks would run in autocommit, where no lock holds.');
        }

        $previousReadsSeeCommits = $this->readsSeeCommits;
        $previousHeld = $this->held;

        try {
            $this->lockRows($orderId, $orderProductIds);

            // Outermost: the picture read from here on is taken after these
            // locks, so a plain read may be trusted. Nested in a transaction a
            // caller opened, whatever an outer run() already set, the lines
            // just locked here may be newer than the picture the caller's
            // transaction already took: only a locking read sees them.
            $this->readsSeeCommits = $outermost;
            $this->held = $outermost ? [$orderId, array_fill_keys($orderProductIds, true)] : $previousHeld;

            $result = $work($connection);
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            if ($this->isLockConflict($exception)) {
                throw new ReturnRequestConflictException(ReturnRequestConflictException::MESSAGE, previous: $exception);
            }

            throw $exception;
        } finally {
            $this->readsSeeCommits = $previousReadsSeeCommits;
            $this->held = $previousHeld;
        }
    }

    /**
     * Lock the order and its lines until the end of the transaction in
     * progress, in the one order every path uses.
     *
     * A no-op inside the work of the outermost run() when that run() already
     * holds the order and every line named - a second round trip per request
     * to hold what is held. Anywhere else the lock is taken for real, on every
     * line named: the transaction it is taken in may be the caller's, whose
     * picture does not show which lines belong to the order.
     *
     * @param list<int> $orderProductIds
     */
    public function lock(int $orderId, array $orderProductIds): void
    {
        if ($this->holds($orderId, $orderProductIds)) {
            return;
        }

        $this->lockRows($orderId, $this->distinctIds($orderProductIds));
    }

    /**
     * @param list<int> $orderProductIds
     */
    private function holds(int $orderId, array $orderProductIds): bool
    {
        if (null === $this->held || $this->held[0] !== $orderId) {
            return false;
        }

        foreach ($orderProductIds as $orderProductId) {
            if (!isset($this->held[1][(int) $orderProductId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the transaction in progress is one run() opened, the rows locked
     * before its first plain read: a plain read then sees every return
     * committed on them.
     */
    #[\Override]
    public function readsSeeCommits(): bool
    {
        return $this->readsSeeCommits;
    }

    /**
     * The ids among $orderProductIds that are lines of the order. A request
     * naming the lines of somebody else's order would otherwise hold them for
     * as long as its transaction lasts; they are left out here, and refused
     * later by the eligibility check like any line that is not the order's.
     *
     * A plain read on the primary key, which takes no lock: a locking one
     * filtered on order_id as well could go through the order_id index and
     * lock the rows and gaps of other orders. A line never moves from one
     * order to another, so reading it before the transaction is safe.
     *
     * @param list<int> $orderProductIds
     *
     * @return list<int>
     */
    private function linesOfOrder(int $orderId, array $orderProductIds): array
    {
        $orderProductIds = $this->distinctIds($orderProductIds);

        if ([] === $orderProductIds) {
            return [];
        }

        $statement = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME)->prepare(\sprintf(
            'SELECT `id` FROM `%s` WHERE `id` IN (%s) AND `order_id` = ?',
            OrderProductTableMap::TABLE_NAME,
            implode(', ', array_fill(0, \count($orderProductIds), '?')),
        ));
        $statement->execute([...$orderProductIds, $orderId]);
        $ids = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
        $statement->closeCursor();

        return $ids;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function distinctIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The order, then its lines by increasing id. Each row also leaves the
     * Propel instance pool, so a model read from here on is read after the
     * lock rather than handed back as it was loaded before it.
     *
     * @param list<int> $orderProductIds lines of the order, see linesOfOrder()
     */
    private function lockRows(int $orderId, array $orderProductIds): void
    {
        $this->lockRow(OrderTableMap::TABLE_NAME, $orderId);
        OrderTableMap::removeInstanceFromPool((string) $orderId);

        sort($orderProductIds);

        foreach ($orderProductIds as $orderProductId) {
            $this->lockRow(OrderProductTableMap::TABLE_NAME, $orderProductId);
            OrderProductTableMap::removeInstanceFromPool((string) $orderProductId);
        }
    }

    /**
     * `order` is a reserved word: the table name is always quoted.
     */
    private function lockRow(string $table, int $id): void
    {
        $statement = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME)
            ->prepare(\sprintf('SELECT `id` FROM `%s` WHERE `id` = :id FOR UPDATE', $table));
        $statement->execute([':id' => $id]);
        $statement->closeCursor();
    }

    /**
     * Propel counts the transactions it nests and PDO knows whether one is
     * open: both have to agree. A transaction opened on the PDO connection
     * behind Propel's back, or one ended by an implicit commit while Propel
     * still counts it, would make the next beginTransaction() either a no-op
     * or a fresh transaction nobody meant: refusing is the only answer that
     * never leaves the checks in autocommit.
     */
    private function isOutsideAnyTransaction(ConnectionInterface $connection): bool
    {
        $openInPdo = $connection->inTransaction();
        $openInPropel = $connection instanceof ConnectionWrapper
            ? $connection->getNestedTransactionCount() > 0
            : $openInPdo;

        if ($openInPdo !== $openInPropel) {
            throw new \LogicException(\sprintf('The database connection is %s a transaction while Propel counts %s: the return cannot be written safely.', $openInPdo ? 'in' : 'out of', $openInPropel ? 'one' : 'none'));
        }

        return !$openInPdo;
    }

    /**
     * A deadlock (1213), or on MariaDB 11.6 and later a row changed since the
     * picture of the transaction was taken (1020), wherever Propel wrapped it.
     */
    private function isLockConflict(\Throwable $exception): bool
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException
                && \in_array((int) ($current->errorInfo[1] ?? 0), [self::ER_LOCK_DEADLOCK, self::ER_CHECKREAD], true)) {
                return true;
            }
        }

        return false;
    }
}
