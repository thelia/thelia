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

namespace Thelia\Domain\Promotion\Coupon\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Promotion\Coupon\Exception\InvalidCouponExclusionException;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Model\CouponExclusion;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponExclusionTableMap;

/**
 * Writes the promotions a promotion never applies with.
 *
 * An exclusion goes both ways: when A excludes B, B excludes A. Both rows are
 * written, and removed, together.
 *
 * Locks first. Two admins saving A and B at the same time touch the rows of each
 * other: every writer therefore locks, before it reads or writes anything of the
 * transaction, the `coupon` rows (by primary key, in increasing id) of the promotion,
 * of the ones it will exclude and of the ones it excluded until now. One order for
 * everybody: two writers cannot each hold what the other waits for. A conflict that
 * still happens (deadlock, or a row changed since the read view) is refused as
 * InvalidCouponExclusionException::concurrentEdit(), never a server error.
 */
final readonly class CouponExclusionWriter
{
    /**
     * Makes these promotions the only ones the promotion excludes.
     *
     * @param list<int|string> $excludedCouponIds
     *
     * @throws InvalidCouponExclusionException when a promotion would exclude itself, an id is unknown, or a concurrent edit got in the way; nothing is written then
     */
    public function replace(int $couponId, array $excludedCouponIds): void
    {
        $excludedCouponIds = array_values(array_unique(array_map(intval(...), $excludedCouponIds)));

        if (\in_array($couponId, $excludedCouponIds, true)) {
            throw InvalidCouponExclusionException::selfExclusion($couponId);
        }

        $connection = Propel::getWriteConnection(CouponExclusionTableMap::DATABASE_NAME);

        // Outside any transaction the rows to lock are read in autocommit, so that the read view
        // of the transaction is not taken before the locks. Inside one, the caller has locked them
        // (see lockRows()); what it did not know is locked below.
        $toLock = $connection->inTransaction()
            ? $this->sorted([$couponId, ...$excludedCouponIds])
            : $this->rowsToLock($couponId, $excludedCouponIds);

        $connection->beginTransaction();

        try {
            $this->lockRows($toLock);
            // Under the lock of the promotion: what is read is what is there.
            $this->lockRows($this->sorted([$couponId, ...$excludedCouponIds, ...$this->currentlyExcludedBy($couponId, $connection)]));

            $this->assertExist($couponId, $excludedCouponIds);

            CouponExclusionQuery::create()->filterByCouponId($couponId)->delete($connection);
            CouponExclusionQuery::create()->filterByExcludedCouponId($couponId)->delete($connection);

            foreach ($excludedCouponIds as $excludedCouponId) {
                $this->insertPair($couponId, $excludedCouponId, $connection);
                $this->insertPair($excludedCouponId, $couponId, $connection);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $this->translate($throwable);
        }
    }

    /**
     * The promotions whose rows a write of this exclusion list touches: the promotion (none yet when
     * it is being created), the ones it will exclude, and the ones it excluded until now.
     *
     * @param list<int> $excludedCouponIds
     *
     * @return list<int> by increasing id
     */
    public function rowsToLock(?int $couponId, array $excludedCouponIds): array
    {
        $current = null === $couponId ? [] : CouponExclusionQuery::create()->excludedCouponIdsFor($couponId);

        return $this->sorted([...(null === $couponId ? [] : [$couponId]), ...$excludedCouponIds, ...$current]);
    }

    /**
     * Locks the `coupon` rows, by primary key and in increasing id. To be called as the very first
     * statement of the transaction that writes the promotion and its exclusions, with rowsToLock()
     * read before the transaction was opened.
     *
     * @param list<int> $couponIds
     *
     * @throws InvalidCouponExclusionException on a deadlock
     */
    public function lockRows(array $couponIds): void
    {
        $connection = Propel::getWriteConnection(CouponExclusionTableMap::DATABASE_NAME);

        try {
            foreach ($this->sorted($couponIds) as $couponId) {
                $statement = $connection->prepare('SELECT `id` FROM `coupon` WHERE `id` = :id FOR UPDATE');
                $statement->execute([':id' => $couponId]);
                $statement->closeCursor();
            }
        } catch (\Throwable $throwable) {
            throw $this->translate($throwable);
        }
    }

    /**
     * @return list<int>
     */
    private function currentlyExcludedBy(int $couponId, ConnectionInterface $connection): array
    {
        $statement = $connection->prepare('SELECT `excluded_coupon_id` FROM `coupon_exclusion` WHERE `coupon_id` = :id FOR UPDATE');
        $statement->execute([':id' => $couponId]);
        $ids = array_map(intval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
        $statement->closeCursor();

        return $ids;
    }

    /**
     * @param list<int> $couponIds
     *
     * @return list<int>
     */
    private function sorted(array $couponIds): array
    {
        $couponIds = array_values(array_unique($couponIds));
        sort($couponIds);

        return $couponIds;
    }

    /**
     * A deadlock (1213), or on MariaDB 11.6 and later a row changed since the read view was taken
     * (1020), wherever Propel wrapped it, becomes a refusal the admin can retry.
     */
    private function translate(\Throwable $throwable): \Throwable
    {
        if ($throwable instanceof InvalidCouponExclusionException) {
            return $throwable;
        }

        return LockConflictDetector::isLockConflict($throwable)
            ? InvalidCouponExclusionException::concurrentEdit($throwable)
            : $throwable;
    }

    /**
     * @param list<int> $excludedCouponIds
     */
    private function assertExist(int $couponId, array $excludedCouponIds): void
    {
        $found = CouponQuery::create()
            ->filterById([$couponId, ...$excludedCouponIds])
            ->select('Id')
            ->find()
            ->toArray();

        if (!\in_array($couponId, array_map(intval(...), $found), true)) {
            throw InvalidCouponExclusionException::unknownCoupon($couponId);
        }

        $unknown = array_values(array_diff($excludedCouponIds, array_map(intval(...), $found)));

        if ([] !== $unknown) {
            throw InvalidCouponExclusionException::unknownCoupons($unknown);
        }
    }

    private function insertPair(int $couponId, int $excludedCouponId, ConnectionInterface $connection): void
    {
        (new CouponExclusion())
            ->setCouponId($couponId)
            ->setExcludedCouponId($excludedCouponId)
            ->save($connection);
    }
}
