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
use Thelia\Model\Map\CartItemTableMap;

/**
 * Reads which lines of a cart are told as held, and with which quantity: the ones that have a
 * `reserved_until`, whether its date is still ahead or ran out, with the `reserved_quantity` they
 * were told with (their quantity when the column is empty: a line held before the column existed).
 * A line whose date ran out holds no stock any more (it is not counted by DatabaseReservedQuantity)
 * but was not released either: nothing told it, until the purge, a removal or an order does, and a
 * hold that takes it back only pushes its date.
 */
final readonly class HeldCartLineReader
{
    private const CHUNK = 500;

    /**
     * @return list<StockReservationLine> by line id; one line only when $cartItemId is given
     */
    public function ofCart(int $cartId, ?int $cartItemId = null, ?ConnectionInterface $connection = null): array
    {
        return $this->ofCarts([$cartId], $cartItemId, $connection)[$cartId] ?? [];
    }

    /**
     * @param list<int> $cartIds
     *
     * @return array<int, list<StockReservationLine>> by cart id; a cart that holds nothing is absent
     */
    public function ofCarts(array $cartIds, ?int $cartItemId = null, ?ConnectionInterface $connection = null): array
    {
        $connection ??= Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $byCart = [];

        foreach (array_chunk(array_values(array_unique(array_map('intval', $cartIds))), self::CHUNK) as $chunk) {
            $statement = $connection->prepare(
                'SELECT `id`, `cart_id`, `product_sale_elements_id`, COALESCE(`reserved_quantity`, `quantity`) FROM `cart_item` WHERE `cart_id` IN ('.implode(',', $chunk).') '
                .(null === $cartItemId ? '' : 'AND `id` = :cartItemId ')
                .'AND `reserved_until` IS NOT NULL ORDER BY `id`',
            );

            if (null !== $cartItemId) {
                $statement->bindValue(':cartItemId', $cartItemId, \PDO::PARAM_INT);
            }

            $statement->execute();

            foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $cartId, $productSaleElementsId, $quantity]) {
                $byCart[(int) $cartId][] = new StockReservationLine((int) $id, (int) $productSaleElementsId, round((float) $quantity, 6));
            }
        }

        return $byCart;
    }

    /**
     * Those of the lines that are told as held, with the quantity they were told with, read with no
     * lock: for a caller that only compares (what a rollback or a later hold left).
     *
     * @param list<int> $cartItemIds
     *
     * @return array<int, float> by line id; a line not told as held is absent
     */
    public function announcedAmong(array $cartItemIds, ?ConnectionInterface $connection = null): array
    {
        if ([] === $cartItemIds) {
            return [];
        }

        $connection ??= Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $announced = [];

        foreach (array_chunk(array_values(array_unique(array_map('intval', $cartItemIds))), self::CHUNK) as $chunk) {
            $statement = $connection->prepare('SELECT `id`, COALESCE(`reserved_quantity`, `quantity`) FROM `cart_item` WHERE `id` IN ('.implode(',', $chunk).') AND `reserved_until` IS NOT NULL');
            $statement->execute();

            foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $quantity]) {
                $announced[(int) $id] = round((float) $quantity, 6);
            }
        }

        return $announced;
    }

    /**
     * Locks the lines by primary key, in ascending id, in the transaction of the caller, and reads
     * them in the very statements that lock them (a locking read sees the row as it is now, never
     * the view the transaction froze): nothing that decides is read before every lock is taken.
     * To be called after the locks of the parent rows (the stock rows) and before any plain read.
     * By primary key only: a lock on the cart or on the date index would take gap locks, and two
     * transactions with nothing in common would then wait on each other.
     *
     * A line another transaction deleted is absent from the answer.
     *
     * @param list<int> $cartItemIds
     *
     * @return array<int, array{productSaleElementsId: int, quantity: float, announced: ?float}> by line id; `announced` is the quantity the line was told with, null when it is not told as held
     */
    public function lockedLines(array $cartItemIds, ConnectionInterface $connection): array
    {
        $cartItemIds = array_values(array_unique(array_map('intval', $cartItemIds)));
        sort($cartItemIds);
        $lines = [];

        foreach (array_chunk($cartItemIds, self::CHUNK) as $chunk) {
            $statement = $connection->prepare(
                'SELECT `id`, `product_sale_elements_id`, `quantity`, `reserved_until` IS NOT NULL, COALESCE(`reserved_quantity`, `quantity`) FROM `cart_item` WHERE `id` IN ('.implode(',', $chunk).') ORDER BY `id` FOR UPDATE',
            );
            $statement->execute();

            foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $productSaleElementsId, $quantity, $told, $toldQuantity]) {
                $lines[(int) $id] = [
                    'productSaleElementsId' => (int) $productSaleElementsId,
                    'quantity' => round((float) $quantity, 6),
                    'announced' => 1 === (int) $told ? round((float) $toldQuantity, 6) : null,
                ];
            }
        }

        // A line read through Propel after the locks must be the row as it is now, not a pooled one.
        CartItemTableMap::clearInstancePool();

        return $lines;
    }

    /**
     * The lines of a cart that are told as held, locked as lockedLines() does. The ids come from a
     * plain read (a line added to the cart after it is missing), the state of each line from the lock.
     *
     * @return list<StockReservationLine>
     */
    public function lockedOfCart(int $cartId, ConnectionInterface $connection): array
    {
        $statement = $connection->prepare('SELECT `id` FROM `cart_item` WHERE `cart_id` = :cartId');
        $statement->bindValue(':cartId', $cartId, \PDO::PARAM_INT);
        $statement->execute();

        $held = [];

        foreach ($this->lockedLines(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN)), $connection) as $id => $line) {
            if (null !== $line['announced']) {
                $held[] = new StockReservationLine($id, $line['productSaleElementsId'], $line['announced']);
            }
        }

        return $held;
    }
}
