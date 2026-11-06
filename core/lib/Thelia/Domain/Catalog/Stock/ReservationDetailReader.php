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
 * The detail of the active reservations of the national counter, for a list of sale elements,
 * read in one query: the quantity held, the number of distinct carts holding it and the
 * nearest expiry.
 *
 * It counts what `DatabaseReservedQuantity` counts for a reader with no cart and no sale unit
 * (same condition, shared): the lines whose reservation is still ahead, of the carts that sell
 * from the national counter. A sale element nothing holds is absent from the answer, and a shop
 * that does not reserve reads nothing: no query.
 */
final readonly class ReservationDetailReader
{
    public function __construct(private StockSettings $stockSettings)
    {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ReservationDetail> by sale element id; a sale element nothing holds is absent
     */
    public function detailOf(array $productSaleElementsIds): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds || !$this->stockSettings->reservationEnabled()) {
            return [];
        }

        $statement = Propel::getConnection(CartItemTableMap::DATABASE_NAME)->prepare($this->query(\count($productSaleElementsIds)));

        foreach ($productSaleElementsIds as $position => $productSaleElementsId) {
            $statement->bindValue($position + 1, $productSaleElementsId, \PDO::PARAM_INT);
        }

        $statement->execute();

        $detail = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$productSaleElementsId, $quantity, $cartCount, $nextExpiry]) {
            $detail[(int) $productSaleElementsId] = new ReservationDetail(
                round((float) $quantity, 6),
                (int) $cartCount,
                new \DateTimeImmutable((string) $nextExpiry),
            );
        }

        return $detail;
    }

    public function query(int $idCount): string
    {
        return 'SELECT `cart_item`.`product_sale_elements_id`, SUM(`cart_item`.`quantity`), COUNT(DISTINCT `cart_item`.`cart_id`), MIN(`cart_item`.`reserved_until`) FROM `cart_item` '
            .'INNER JOIN `cart` ON `cart`.`id` = `cart_item`.`cart_id` '
            .DatabaseReservedQuantity::OWN_STOCK_JOIN
            .'WHERE `cart_item`.`product_sale_elements_id` IN ('.implode(',', array_fill(0, $idCount, '?')).') '
            .'AND '.DatabaseReservedQuantity::ACTIVE_RESERVATION.' '
            .'AND '.DatabaseReservedQuantity::NATIONAL_STOCK.' '
            .'GROUP BY `cart_item`.`product_sale_elements_id`';
    }
}
