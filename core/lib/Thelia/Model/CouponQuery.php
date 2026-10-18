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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Base\CouponQuery as BaseCouponQuery;
use Thelia\Model\Map\CouponTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'coupon' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CouponQuery extends BaseCouponQuery
{
    /**
     * Takes one usage off a limited coupon, unless it has none left.
     *
     * The check and the write are a single statement: two orders paid at the
     * same time cannot both take the last usage.
     *
     * @return bool whether a usage was taken
     */
    public function takeOneUsage(int $couponId, ?ConnectionInterface $connection = null): bool
    {
        return 1 === $this->executeUsageUpdate(
            'UPDATE coupon SET max_usage = max_usage - 1 WHERE id = :id AND max_usage > 0',
            $couponId,
            $connection,
        );
    }

    /**
     * Gives one usage back to a limited coupon. An unlimited coupon is left as it is.
     *
     * @return bool whether a usage was given back
     */
    public function giveBackOneUsage(int $couponId, ?ConnectionInterface $connection = null): bool
    {
        return 1 === $this->executeUsageUpdate(
            'UPDATE coupon SET max_usage = max_usage + 1 WHERE id = :id AND max_usage >= 0',
            $couponId,
            $connection,
        );
    }

    /**
     * The usages the coupon has left, read from the database rather than from an
     * object loaded before the last update.
     */
    public function findMaxUsage(int $couponId, ?ConnectionInterface $connection = null): ?int
    {
        $connection ??= Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $statement = $connection->prepare('SELECT max_usage FROM coupon WHERE id = :id');
        $statement->execute([':id' => $couponId]);
        $maxUsage = $statement->fetchColumn();

        return false === $maxUsage ? null : (int) $maxUsage;
    }

    private function executeUsageUpdate(string $sql, int $couponId, ?ConnectionInterface $connection): int
    {
        $connection ??= Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $statement = $connection->prepare($sql);
        $statement->execute([':id' => $couponId]);

        return $statement->rowCount();
    }
}

// CouponQuery
