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
use Thelia\Model\Base\CouponCustomerCountQuery as BaseCouponCustomerCountQuery;
use Thelia\Model\Map\CouponCustomerCountTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'coupon_customer_count' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CouponCustomerCountQuery extends BaseCouponCustomerCountQuery
{
    /**
     * Counts one more usage of a coupon for a customer, unless the customer has
     * already used it as many times as the coupon allows.
     *
     * The check and the write are a single statement: two orders of the same
     * customer paid at the same time cannot both take the last usage.
     *
     * @return bool whether a usage was counted
     */
    public function takeOneUsage(int $couponId, int $customerId, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getWriteConnection(CouponCustomerCountTableMap::DATABASE_NAME);

        $connection->prepare('INSERT IGNORE INTO coupon_customer_count (coupon_id, customer_id, count) VALUES (:coupon, :customer, 0)')
            ->execute([':coupon' => $couponId, ':customer' => $customerId]);

        $statement = $connection->prepare(
            'UPDATE coupon_customer_count SET count = count + 1'
            .' WHERE coupon_id = :coupon AND customer_id = :customer'
            .' AND count < (SELECT max_usage FROM coupon WHERE id = :limit_coupon)',
        );
        $statement->execute([':coupon' => $couponId, ':customer' => $customerId, ':limit_coupon' => $couponId]);

        return $this->forgetLoadedCounts(1 === $statement->rowCount());
    }

    /**
     * Gives one usage of a coupon back to a customer who has used it.
     *
     * @return bool whether a usage was given back
     */
    public function giveBackOneUsage(int $couponId, int $customerId, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getWriteConnection(CouponCustomerCountTableMap::DATABASE_NAME);

        $statement = $connection->prepare(
            'UPDATE coupon_customer_count SET count = count - 1'
            .' WHERE coupon_id = :coupon AND customer_id = :customer AND count > 0',
        );
        $statement->execute([':coupon' => $couponId, ':customer' => $customerId]);

        return $this->forgetLoadedCounts(1 === $statement->rowCount());
    }

    /**
     * The rows were written behind Propel's back: a count already loaded in this
     * request must be read again from the database.
     */
    private function forgetLoadedCounts(bool $changed): bool
    {
        CouponCustomerCountTableMap::clearInstancePool();

        return $changed;
    }
}

// CouponCustomerCountQuery
