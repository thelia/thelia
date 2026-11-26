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

namespace Thelia\Domain\SaleUnit\Stock;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Model\SaleUnitProductSaleElements;
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * Writes the own stock a sale unit keeps of a sale element, and the threshold it runs low on, for
 * an editor of the back office: a change of the quantity reaches the listeners of the stock of the
 * sale units (SALE_UNIT_STOCK_CHANGED, the low stock alert) as a sale does.
 *
 * A quantity left empty removes the row: the unit sells from the national counter again. A row
 * created or removed is no movement of a stock and is not told.
 */
final readonly class SaleUnitStockWriter
{
    public function __construct(private StockLevelAnnouncer $stockLevelAnnouncer)
    {
    }

    /**
     * @param float|null $lowStockThreshold null: the threshold of the sale element applies, then the shop-wide one
     *
     * @return bool whether the row was written or removed
     *
     * @throws \InvalidArgumentException when the threshold is negative
     */
    public function write(int $saleUnitId, int $productSaleElementsId, ?float $quantity, ?float $lowStockThreshold = null, ?ConnectionInterface $connection = null): bool
    {
        if (null !== $lowStockThreshold && $lowStockThreshold < 0) {
            throw new \InvalidArgumentException('A low stock threshold is never negative.');
        }

        $row = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($saleUnitId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne($connection);

        if (null === $quantity) {
            if (null === $row) {
                return false;
            }

            $row->delete($connection);

            return true;
        }

        $before = null === $row ? null : (float) $row->getQuantity();
        $row ??= (new SaleUnitProductSaleElements())
            ->setSaleUnitId($saleUnitId)
            ->setProductSaleElementsId($productSaleElementsId);

        $written = $row
            ->setQuantity(number_format($quantity, 6, '.', ''))
            ->setLowStockThreshold($lowStockThreshold)
            ->save($connection) > 0;

        if (null !== $before) {
            // Once the transaction of the caller, if any, is over: never when it is rolled back.
            $this->stockLevelAnnouncer->announce($productSaleElementsId, $before, (float) $row->getQuantity(), $saleUnitId);
        }

        return $written;
    }
}
