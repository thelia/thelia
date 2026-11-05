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

use Propel\Runtime\Propel;
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
 */
final readonly class StockReservationPurger
{
    public const DEFAULT_BATCH_SIZE = 500;

    public function __construct(private StockSettings $stockSettings)
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

            // A line held again since it was found keeps its new date.
            $cleared += (int) $connection->exec('UPDATE `cart_item` SET `reserved_until` = NULL WHERE `id` IN ('.implode(',', $ids).') AND `reserved_until` <= NOW()');
        } while (\count($ids) === $batchSize);

        return $cleared;
    }
}
