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

namespace Thelia\Domain\Catalog\Stock;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Locks the stock rows of sale elements, by primary key and in ascending id, in the
 * transaction of the caller: what every transaction that reserves or takes stock does
 * before it reads anything, so that two of them on the same sale element follow one another.
 *
 * By primary key only: a locking read on a non-unique index (the lines of a cart, the
 * reservations of a sale element) takes gap locks, and two transactions with nothing in
 * common would then wait on each other. The lock is taken before the first plain read of
 * the transaction, which freezes its view: a reservation another transaction committed
 * while this one waited is seen.
 */
final readonly class StockRowLocker
{
    /**
     * @param list<int> $productSaleElementsIds
     */
    public function lock(array $productSaleElementsIds, ConnectionInterface $connection): void
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return;
        }

        sort($productSaleElementsIds);

        $connection
            ->query('SELECT `id` FROM `product_sale_elements` WHERE `id` IN ('.implode(',', $productSaleElementsIds).') ORDER BY `id` FOR UPDATE')
            ->fetchAll();

        // A sale element read through Propel after the lock must be the row as it is now, not
        // the instance a read before the lock left in the pool.
        ProductSaleElementsTableMap::clearInstancePool();
    }
}
