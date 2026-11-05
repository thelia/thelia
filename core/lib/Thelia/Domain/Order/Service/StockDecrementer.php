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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\PreorderRule;
use Thelia\Domain\Order\Exception\StockTakeRefusedException;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Exception\TheliaProcessException;

/**
 * Decrements product sale elements stock with a single conditional UPDATE,
 * letting the database arbitrate concurrent checkouts. This closes the
 * read-check-write race of the historical flow, where two simultaneous
 * orders could both pass the availability check and silently oversell.
 *
 * A sale unit may keep its own stock: when a unit id is passed and that unit has
 * a row for the sale element, the very same conditional UPDATE runs against
 * `sale_unit_product_sale_elements` instead — same atomicity, same guard. With no
 * unit, or a unit that keeps no row for the sale element, the shared national
 * counter is decremented, exactly as before the feature.
 *
 * The guard reads the sale mode of the sale element at zero stock in the UPDATE itself:
 * a take costs the one statement it always did. What the takes left is read once after
 * all of them, for every row they moved.
 */
readonly class StockDecrementer
{
    private const SALE_UNIT_TABLE = 'sale_unit_product_sale_elements';

    /** What the guard allows below zero, written against the row of the sale element aliased `pse`. */
    private const ALLOWED_BELOW_ZERO = 'CASE WHEN `pse`.`out_of_stock_policy` = '.OutOfStockPolicy::Limited->value
        .' THEN GREATEST(COALESCE(`pse`.`preorder_limit`, 0), 0) ELSE 0 END';

    public function __construct(private SaleUnitStockResolver $saleUnitStockResolver)
    {
    }

    /**
     * @param bool     $guardAvailability    when true (and negative stock is not allowed), an
     *                                       insufficient stock aborts with a TheliaProcessException
     *                                       instead of silently clamping to zero. What is insufficient
     *                                       depends on the sale mode of the sale element at zero stock:
     *                                       the stock itself when it refuses, the stock plus its preorder
     *                                       limit when it is limited, nothing when it is unlimited
     * @param int|null $saleUnitId           the sale unit the movement belongs to, or null for the
     *                                       shared national stock
     * @param float    $reservedByOtherCarts what the carts other than the one being ordered hold of
     *                                       the same stock, read after its row was locked: the guard
     *                                       leaves it to them, and a unit beyond it is a preorder
     *
     * @return StockMovement|null the stock before and after, null when nothing was moved
     *
     * @throws TheliaProcessException
     */
    public function decrement(
        int $productSaleElementsId,
        float $quantity,
        bool $guardAvailability,
        bool $allowNegativeStock,
        ConnectionInterface $connection,
        ?int $saleUnitId = null,
        float $reservedByOtherCarts = 0.0,
    ): ?StockMovement {
        return $this->decrementAll(
            [new StockTake($productSaleElementsId, $quantity, $guardAvailability, $reservedByOtherCarts)],
            $allowNegativeStock,
            $connection,
            $saleUnitId,
        )[0];
    }

    /**
     * Takes every quantity, in the order given (the ascending id of the sale element, for the
     * locks), then reads once what they left.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, StockTake> $takes
     *
     * @return array<TKey, StockMovement|null> what each take did, by the key of the take; null when it moved nothing
     *
     * @throws StockTakeRefusedException the guard of a take found too little stock; the takes before it are not undone
     */
    public function decrementAll(array $takes, bool $allowNegativeStock, ConnectionInterface $connection, ?int $saleUnitId = null): array
    {
        $movements = array_map(static fn (): ?StockMovement => null, $takes);
        $takes = array_filter($takes, static fn (StockTake $take): bool => $take->quantity > 0);

        if ([] === $takes) {
            return $movements;
        }

        /** @var array<TKey, bool> $onSaleUnitStock */
        $onSaleUnitStock = [];

        foreach ($takes as $key => $take) {
            $onSaleUnitStock[$key] = $this->movesSaleUnitStock($saleUnitId, $take->productSaleElementsId);
        }

        // Historical behaviour when availability is not checked: never fail, floor the
        // stock at zero. That path cannot tell the stock it found from the one it left.
        $clampedBefore = [];

        foreach ($takes as $key => $take) {
            if (!$allowNegativeStock && !$take->guardAvailability) {
                $clampedBefore = $this->rowsOf($takes, $onSaleUnitStock, $saleUnitId, $connection);

                break;
            }
        }

        foreach ($takes as $key => $take) {
            if (!$this->applyTake($take, $onSaleUnitStock[$key], $allowNegativeStock, $connection, $saleUnitId)) {
                throw new StockTakeRefusedException($key);
            }
        }

        // Our own UPDATEs hold the rows: what is read now is what they left.
        $after = $this->rowsOf($takes, $onSaleUnitStock, $saleUnitId, $connection);

        // From what each row left back to what it held: a take found what the next take on the
        // same row found, plus what it took. A stock floored at zero cannot be walked back: it is
        // then walked forward from what was read before the takes.
        $levels = [] === $clampedBefore ? self::walkedBack($takes, $onSaleUnitStock, $after) : self::walkedForward($takes, $onSaleUnitStock, $clampedBefore, $allowNegativeStock);

        foreach ($takes as $key => $take) {
            $target = self::targetKey($take->productSaleElementsId, $onSaleUnitStock[$key]);

            if (!isset($after[$target], $levels[$key])) {
                continue;
            }

            [, $policy, $restockDate] = $after[$target];
            [$previous, $new] = $levels[$key];
            $preorder = $take->guardAvailability && PreorderRule::isPreorder($take->quantity, $previous, $take->reservedByOtherCarts, $policy);

            $movements[$key] = new StockMovement(
                $take->productSaleElementsId,
                $onSaleUnitStock[$key] ? $saleUnitId : null,
                $previous,
                $new,
                $preorder,
                $preorder ? $restockDate : null,
            );
        }

        return $movements;
    }

    /**
     * Puts a quantity back in stock with the same single UPDATE, so that every stock
     * mutation of the core goes through this class.
     */
    public function increment(int $productSaleElementsId, float $quantity, ConnectionInterface $connection, ?int $saleUnitId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $onSaleUnitStock = $this->movesSaleUnitStock($saleUnitId, $productSaleElementsId);
        $sql = $onSaleUnitStock
            ? 'UPDATE `'.self::SALE_UNIT_TABLE.'` SET `quantity` = `quantity` + :quantity WHERE `sale_unit_id` = :saleUnitId AND `product_sale_elements_id` = :id'
            : 'UPDATE `product_sale_elements` SET `quantity` = `quantity` + :quantity WHERE `id` = :id';

        $statement = $connection->prepare($sql);
        $statement->bindValue(':quantity', $quantity);
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);

        if ($onSaleUnitStock) {
            $statement->bindValue(':saleUnitId', $saleUnitId, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * Whether a movement for this sale unit lands on the unit's own stock row rather
     * than on the shared national counter.
     */
    public function movesSaleUnitStock(?int $saleUnitId, int $productSaleElementsId): bool
    {
        return null !== $saleUnitId && $this->saleUnitStockResolver->hasOwnStock($saleUnitId, $productSaleElementsId);
    }

    /**
     * The one statement of a take: false when its guard refused it.
     *
     * The guard is written on the row: the stock itself when the sale element refuses at
     * zero, the stock plus its preorder limit when it is limited, nothing when it is
     * unlimited; less what the other carts hold, when they hold something.
     */
    protected function applyTake(StockTake $take, bool $onSaleUnitStock, bool $allowNegativeStock, ConnectionInterface $connection, ?int $saleUnitId): bool
    {
        $guarded = $take->guardAvailability && !$allowNegativeStock;
        $stock = $onSaleUnitStock ? '`own`.`quantity`' : '`pse`.`quantity`';

        $set = $allowNegativeStock || $guarded ? "$stock = $stock - :quantity" : "$stock = GREATEST($stock - :quantity, 0)";

        // The row of the sale element is joined only when the guard reads its sale mode.
        $table = match (true) {
            $onSaleUnitStock && $guarded => '`'.self::SALE_UNIT_TABLE.'` `own` INNER JOIN `product_sale_elements` `pse` ON `pse`.`id` = `own`.`product_sale_elements_id`',
            $onSaleUnitStock => '`'.self::SALE_UNIT_TABLE.'` `own`',
            default => '`product_sale_elements` `pse`',
        };
        $where = $onSaleUnitStock
            ? '`own`.`sale_unit_id` = :saleUnitId AND `own`.`product_sale_elements_id` = :id'
            : '`pse`.`id` = :id';

        if ($guarded) {
            // What other carts hold is theirs: only a reservation that is there changes the statement.
            $reserved = $take->reservedByOtherCarts > 0 ? ' - :reserved' : '';
            $where .= ' AND (`pse`.`out_of_stock_policy` = '.OutOfStockPolicy::Unlimited->value
                ." OR $stock + ".self::ALLOWED_BELOW_ZERO."$reserved >= :quantity)";
        }

        $statement = $connection->prepare("UPDATE $table SET $set WHERE $where");
        $statement->bindValue(':quantity', $take->quantity);
        $statement->bindValue(':id', $take->productSaleElementsId, \PDO::PARAM_INT);

        if ($guarded && $take->reservedByOtherCarts > 0) {
            $statement->bindValue(':reserved', $take->reservedByOtherCarts);
        }

        if ($onSaleUnitStock) {
            $statement->bindValue(':saleUnitId', $saleUnitId, \PDO::PARAM_INT);
        }

        $statement->execute();

        return !$guarded || $statement->rowCount() > 0;
    }

    /**
     * The stock of every row the takes move, with the sale mode and the restock date of their
     * sale element: one query for the national counters, one for the own stock of the sale unit.
     *
     * @param array<array-key, StockTake> $takes
     * @param array<array-key, bool>      $onSaleUnitStock
     *
     * @return array<string, array{0: float, 1: OutOfStockPolicy, 2: ?string}> by target key
     */
    private function rowsOf(array $takes, array $onSaleUnitStock, ?int $saleUnitId, ConnectionInterface $connection): array
    {
        $ids = [false => [], true => []];

        foreach ($takes as $key => $take) {
            $ids[$onSaleUnitStock[$key]][] = $take->productSaleElementsId;
        }

        $rows = [];

        foreach ($ids as $ownStock => $productSaleElementsIds) {
            if ([] === $productSaleElementsIds) {
                continue;
            }

            $in = implode(',', array_unique(array_map('intval', $productSaleElementsIds)));
            $statement = $ownStock
                ? $connection->prepare(
                    'SELECT `own`.`product_sale_elements_id`, `own`.`quantity`, `pse`.`out_of_stock_policy`, `pse`.`restock_date` '
                    .'FROM `'.self::SALE_UNIT_TABLE.'` `own` INNER JOIN `product_sale_elements` `pse` ON `pse`.`id` = `own`.`product_sale_elements_id` '
                    ."WHERE `own`.`sale_unit_id` = :saleUnitId AND `own`.`product_sale_elements_id` IN ($in)",
                )
                : $connection->prepare("SELECT `id`, `quantity`, `out_of_stock_policy`, `restock_date` FROM `product_sale_elements` WHERE `id` IN ($in)");

            if ($ownStock) {
                $statement->bindValue(':saleUnitId', $saleUnitId, \PDO::PARAM_INT);
            }

            $statement->execute();

            foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $quantity, $policy, $restockDate]) {
                $rows[self::targetKey((int) $id, (bool) $ownStock)] = [
                    round((float) $quantity, 6),
                    OutOfStockPolicy::fromStored(null === $policy ? null : (int) $policy),
                    null === $restockDate ? null : substr((string) $restockDate, 0, 10),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<array-key, StockTake>                                     $takes
     * @param array<array-key, bool>                                          $onSaleUnitStock
     * @param array<string, array{0: float, 1: OutOfStockPolicy, 2: ?string}> $after
     *
     * @return array<array-key, array{0: float, 1: float}> the stock each take found and left
     */
    private static function walkedBack(array $takes, array $onSaleUnitStock, array $after): array
    {
        $levels = [];
        $running = array_map(static fn (array $row): float => $row[0], $after);

        foreach (array_reverse($takes, true) as $key => $take) {
            $target = self::targetKey($take->productSaleElementsId, $onSaleUnitStock[$key]);

            if (!isset($running[$target])) {
                continue;
            }

            $levels[$key] = [round($running[$target] + $take->quantity, 6), $running[$target]];
            $running[$target] = $levels[$key][0];
        }

        return $levels;
    }

    /**
     * @param array<array-key, StockTake>                                     $takes
     * @param array<array-key, bool>                                          $onSaleUnitStock
     * @param array<string, array{0: float, 1: OutOfStockPolicy, 2: ?string}> $before
     *
     * @return array<array-key, array{0: float, 1: float}> the stock each take found and left
     */
    private static function walkedForward(array $takes, array $onSaleUnitStock, array $before, bool $allowNegativeStock): array
    {
        $levels = [];
        $running = array_map(static fn (array $row): float => $row[0], $before);

        foreach ($takes as $key => $take) {
            $target = self::targetKey($take->productSaleElementsId, $onSaleUnitStock[$key]);

            if (!isset($running[$target])) {
                continue;
            }

            $left = round($running[$target] - $take->quantity, 6);
            $left = !$allowNegativeStock && !$take->guardAvailability ? max(0.0, $left) : $left;
            $levels[$key] = [$running[$target], $left];
            $running[$target] = $left;
        }

        return $levels;
    }

    private static function targetKey(int $productSaleElementsId, bool $onSaleUnitStock): string
    {
        return ($onSaleUnitStock ? 'own:' : 'pse:').$productSaleElementsId;
    }
}
