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
use Propel\Runtime\Propel;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Model\Map\CartItemTableMap;

/**
 * Clears the reservations that have run out, so that the lines stop carrying a date nobody
 * reads any more. Housekeeping only: an expired reservation is never counted, purged or not.
 *
 * A shop that does not reserve has nothing to clear: the answer is immediate, no query is run. The
 * lines are found through `idx_cart_item_reserved_until`, oldest first, so that the scan reads the
 * expired dates only and never the carts being filled.
 *
 * By batches of lines found without lock, then cleared by primary key: a single UPDATE over
 * the dates would lock every line it scans, the carts being filled at that moment included.
 *
 * A reservation that ran out was never told as released: the purge tells it (STOCK_RELEASED, one
 * event per cart, with the quantity the line was told with), for the lines it really cleared. A line held again since it was found keeps its
 * date and is told to nobody. Each batch is one transaction (lines locked by primary key, read again
 * under the lock, cleared), told after its commit as it was written, never read back: a hold of the
 * same line waits for the commit, or has finished before it. No transaction of the caller is open here: the announcer tells at once.
 */
final readonly class StockReservationPurger
{
    public const DEFAULT_BATCH_SIZE = 500;

    public function __construct(private StockSettings $stockSettings, private ?StockReservationAnnouncer $announcer = null)
    {
    }

    /**
     * @return int the lines cleared
     */
    public function purgeExpired(int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        if (!$this->stockSettings->reservationEnabled()) {
            return 0;
        }

        $batchSize = max(1, $batchSize);
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $find = $connection->prepare('SELECT `id` FROM `cart_item` WHERE `reserved_until` <= NOW() ORDER BY `reserved_until`, `id` LIMIT :batchSize');
        $find->bindValue(':batchSize', $batchSize, \PDO::PARAM_INT);
        $cleared = 0;

        do {
            $find->execute();
            $ids = array_map('intval', $find->fetchAll(\PDO::FETCH_COLUMN));
            $find->closeCursor();

            if ([] === $ids) {
                break;
            }

            try {
                $released = $this->clear($ids, $connection);
            } catch (\Throwable $throwable) {
                if (LockConflictDetector::isLockConflict($throwable)) {
                    // Housekeeping: a line another request holds is left to the next run.
                    break;
                }

                throw $throwable;
            }

            // After the commit: a listener cannot hold the locks, nor fail the clearing. Told as it
            // was written, with no read back: a hold that took the line again since the commit has
            // told its own reservation, which a read back would mistake for "not released".
            foreach ($released as $cartId => $lines) {
                $cleared += \count($lines);
                $this->announcer?->releasedNow($cartId, $lines);
            }
        } while (\count($ids) === $batchSize);

        return $cleared;
    }

    /**
     * One transaction: the lines are locked by primary key in ascending id and read again under the
     * lock, so that what is cleared is what is told (the quantity the line was told with, and only
     * a line still expired: one held again since keeps its date), then cleared.
     *
     * @param list<int> $ids
     *
     * @return array<int, list<StockReservationLine>> the lines cleared, by cart id
     */
    private function clear(array $ids, ConnectionInterface $connection): array
    {
        sort($ids);
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'SELECT `id`, `cart_id`, `product_sale_elements_id`, COALESCE(`reserved_quantity`, `quantity`) FROM `cart_item` WHERE `id` IN ('.implode(',', $ids).') ORDER BY `id` FOR UPDATE',
            );
            $statement->execute();
            $locked = $statement->fetchAll(\PDO::FETCH_NUM);
            CartItemTableMap::clearInstancePool();

            $statement = $connection->prepare('SELECT `id` FROM `cart_item` WHERE `id` IN ('.implode(',', $ids).') AND `reserved_until` <= NOW()');
            $statement->execute();
            $expired = array_flip(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
            $byCart = [];

            if ([] !== $expired) {
                $connection->exec('UPDATE `cart_item` SET `reserved_until` = NULL, `reserved_quantity` = NULL, `reserved_since` = NULL WHERE `id` IN ('.implode(',', array_keys($expired)).')');
            }

            foreach ($locked as [$id, $cartId, $productSaleElementsId, $quantity]) {
                if (isset($expired[(int) $id])) {
                    $byCart[(int) $cartId][] = new StockReservationLine((int) $id, (int) $productSaleElementsId, round((float) $quantity, 6));
                }
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return $byCart;
    }
}
