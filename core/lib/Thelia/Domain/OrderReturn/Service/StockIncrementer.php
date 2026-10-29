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

namespace Thelia\Domain\OrderReturn\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;

/**
 * Puts returned quantities back into stock with a single atomic UPDATE, through
 * StockDecrementer so concurrent returns and checkouts stay consistent without a
 * read-modify-write race.
 *
 * A return goes back where the sale took it from: the stock of the sale unit the
 * order was placed on when the unit keeps a row for the sale element, the shared
 * national counter otherwise.
 */
readonly class StockIncrementer
{
    public function __construct(private StockDecrementer $stockDecrementer)
    {
    }

    public function increment(
        int $productSaleElementsId,
        float $quantity,
        ConnectionInterface $connection,
        ?int $saleUnitId = null,
    ): void {
        $this->stockDecrementer->increment($productSaleElementsId, $quantity, $connection, $saleUnitId);
    }

    /**
     * Puts several quantities back, in the ascending id of their sale element: the order the
     * placement of an order locked the stock rows in, so that two writers moving the same rows
     * never wait for each other the other way round.
     *
     * @param list<array{0: int, 1: float}> $quantities sale element and quantity
     */
    public function incrementInAscendingId(array $quantities, ConnectionInterface $connection, ?int $saleUnitId = null): void
    {
        usort($quantities, static fn (array $first, array $second): int => $first[0] <=> $second[0]);

        foreach ($quantities as [$productSaleElementsId, $quantity]) {
            $this->increment($productSaleElementsId, $quantity, $connection, $saleUnitId);
        }
    }

    /**
     * What a returned quantity of a bundle line gives back: the components of the pack,
     * each at its share. The sale element of the pack holds no stock, and is left alone.
     *
     * A component line holds the quantity of the component for the whole quantity of the
     * pack bought, so returning two packs out of five puts back two fifths of it: a
     * component of three per pack comes back six at a time, whatever the number of packs
     * returned. A pack line with no quantity gives nothing back.
     *
     * @return list<array{0: int, 1: float}> sale element and quantity, for incrementInAscendingId()
     */
    public function componentsOfBundle(OrderProduct $bundleLine, float $returnedQuantity, ConnectionInterface $connection): array
    {
        $boughtQuantity = (float) $bundleLine->getQuantity();

        if ($returnedQuantity <= 0 || $boughtQuantity <= 0) {
            return [];
        }

        $quantities = [];

        foreach (OrderProductQuery::create()
            ->filterByOrderId($bundleLine->getOrderId())
            ->filterByParent($bundleLine->getId())
            ->filterByLineType(OrderProduct::LINE_TYPE_BUNDLE_COMPONENT)
            ->orderById()
            ->find($connection) as $componentLine) {
            $productSaleElementsId = $componentLine->getProductSaleElementsId();

            if (null === $productSaleElementsId) {
                continue;
            }

            $quantities[] = [(int) $productSaleElementsId, round((float) $componentLine->getQuantity() * $returnedQuantity / $boughtQuantity, 6)];
        }

        return $quantities;
    }
}
