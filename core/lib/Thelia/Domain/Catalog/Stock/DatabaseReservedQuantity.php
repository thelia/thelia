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
use Thelia\Model\Map\CartItemTableMap;

/**
 * What the carts hold, read from the lines they reserved: the sum of the quantities of the
 * lines whose `reserved_until` is still ahead, on the same stock as the cart asking.
 *
 * The stock is the own stock of the sale unit of the cart when that unit keeps a row for the
 * sale element, the national counter otherwise; a cart of a unit that keeps no row for it
 * shares the national counter with the carts that have no unit. An expired reservation is
 * never counted, so the answer never waits for the purge.
 *
 * A shop that does not reserve reads nothing: no query, nothing held.
 */
final readonly class DatabaseReservedQuantity implements ReservedQuantityProvider
{
    /** A line counts while its reservation is still ahead; an expired one is never counted, whatever the purge did. */
    public const ACTIVE_RESERVATION = '`cart_item`.`reserved_until` > NOW()';

    /** Joins the own stock row of the sale unit of the cart, when that unit keeps one for the sale element. */
    public const OWN_STOCK_JOIN = 'LEFT JOIN `sale_unit_product_sale_elements` `own` ON `own`.`sale_unit_id` = `cart`.`sale_unit_id` AND `own`.`product_sale_elements_id` = `cart_item`.`product_sale_elements_id` ';

    /** Needs OWN_STOCK_JOIN: the carts that sell from the national counter. */
    public const NATIONAL_STOCK = '`own`.`id` IS NULL';

    public function __construct(private StockSettings $stockSettings)
    {
    }

    public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
    {
        return $this->reservedByOtherCartsOf([$productSaleElementsId], $saleUnitId, $excludedCartId)[$productSaleElementsId] ?? 0.0;
    }

    /**
     * The same answer for several sale elements, in one query.
     *
     * @param list<int>                $productSaleElementsIds
     * @param ConnectionInterface|null $connection             the connection of the transaction that locked the stock rows, when there is one
     *
     * @return array<int, float> by sale element id; a sale element nothing holds is absent
     */
    public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId, ?ConnectionInterface $connection = null): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds || !$this->stockSettings->reservationEnabled()) {
            return [];
        }

        // The own row of the unit of the cart asking decides the stock it sells from: that row,
        // or the national counter.
        $sameStock = null === $saleUnitId
            ? self::NATIONAL_STOCK
            : '((`asker`.`id` IS NOT NULL AND `cart`.`sale_unit_id` = :saleUnitId) OR (`asker`.`id` IS NULL AND `own`.`id` IS NULL))';
        $askerJoin = null === $saleUnitId
            ? ''
            : 'LEFT JOIN `sale_unit_product_sale_elements` `asker` ON `asker`.`sale_unit_id` = :askerSaleUnitId AND `asker`.`product_sale_elements_id` = `cart_item`.`product_sale_elements_id` ';

        $statement = ($connection ?? Propel::getConnection(CartItemTableMap::DATABASE_NAME))->prepare(
            'SELECT `cart_item`.`product_sale_elements_id`, SUM(`cart_item`.`quantity`) FROM `cart_item` '
            .'INNER JOIN `cart` ON `cart`.`id` = `cart_item`.`cart_id` '
            .self::OWN_STOCK_JOIN
            .$askerJoin
            .'WHERE `cart_item`.`product_sale_elements_id` IN ('.implode(',', $productSaleElementsIds).') '
            .'AND '.self::ACTIVE_RESERVATION.' '
            .(null === $excludedCartId ? '' : 'AND `cart_item`.`cart_id` <> :excludedCartId ')
            .'AND '.$sameStock.' '
            .'GROUP BY `cart_item`.`product_sale_elements_id`',
        );

        if (null !== $saleUnitId) {
            $statement->bindValue(':saleUnitId', $saleUnitId, \PDO::PARAM_INT);
            $statement->bindValue(':askerSaleUnitId', $saleUnitId, \PDO::PARAM_INT);
        }

        if (null !== $excludedCartId) {
            $statement->bindValue(':excludedCartId', $excludedCartId, \PDO::PARAM_INT);
        }

        $statement->execute();

        $reserved = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$productSaleElementsId, $quantity]) {
            $reserved[(int) $productSaleElementsId] = round((float) $quantity, 6);
        }

        return $reserved;
    }
}
