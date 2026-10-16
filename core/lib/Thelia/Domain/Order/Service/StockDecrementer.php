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
 */
readonly class StockDecrementer
{
    private const SALE_UNIT_TABLE = 'sale_unit_product_sale_elements';

    public function __construct(private SaleUnitStockResolver $saleUnitStockResolver)
    {
    }

    /**
     * @param bool     $guardAvailability when true (and negative stock is not allowed), an
     *                                    insufficient stock aborts with a TheliaProcessException
     *                                    instead of silently clamping to zero
     * @param int|null $saleUnitId        the sale unit the movement belongs to, or null for the
     *                                    shared national stock
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
    ): void {
        if ($quantity <= 0) {
            return;
        }

        [$table, $where] = $this->target($saleUnitId, $productSaleElementsId);

        if ($allowNegativeStock) {
            $sql = "UPDATE `$table` SET `quantity` = `quantity` - :quantity WHERE $where";
        } elseif ($guardAvailability) {
            $sql = "UPDATE `$table` SET `quantity` = `quantity` - :quantity WHERE $where AND `quantity` >= :quantity";
        } else {
            // Historical behaviour when availability is not checked: never
            // fail, floor the stock at zero.
            $sql = "UPDATE `$table` SET `quantity` = GREATEST(`quantity` - :quantity, 0) WHERE $where";
        }

        $statement = $connection->prepare($sql);
        $statement->bindValue(':quantity', $quantity);
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);

        if (self::SALE_UNIT_TABLE === $table) {
            $statement->bindValue(':saleUnitId', $saleUnitId, \PDO::PARAM_INT);
        }

        $statement->execute();

        if ($guardAvailability && !$allowNegativeStock && 0 === $statement->rowCount()) {
            throw new TheliaProcessException('Not enough stock');
        }
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

        [$table, $where] = $this->target($saleUnitId, $productSaleElementsId);

        $statement = $connection->prepare("UPDATE `$table` SET `quantity` = `quantity` + :quantity WHERE $where");
        $statement->bindValue(':quantity', $quantity);
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);

        if (self::SALE_UNIT_TABLE === $table) {
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
     * The table and WHERE clause the movement targets: the unit's own stock row when
     * it keeps one, the shared national counter otherwise (the fallback the feature
     * stands on).
     *
     * @return array{string, string}
     */
    private function target(?int $saleUnitId, int $productSaleElementsId): array
    {
        if ($this->movesSaleUnitStock($saleUnitId, $productSaleElementsId)) {
            return [self::SALE_UNIT_TABLE, '`sale_unit_id` = :saleUnitId AND `product_sale_elements_id` = :id'];
        }

        return ['product_sale_elements', '`id` = :id'];
    }
}
