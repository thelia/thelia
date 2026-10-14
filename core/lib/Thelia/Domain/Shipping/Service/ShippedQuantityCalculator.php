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

namespace Thelia\Domain\Shipping\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderShipmentTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderShipment;

/**
 * How much of each line of an order its shipments already hold, and how much is left.
 *
 * What a shipment holds is read from its items every time, grouped in one query for
 * the whole order, never kept as a counter on the order line: a counter drifts the
 * first time a shipment is cancelled by hand in the database or a line is edited.
 * A draft holds its lines as firmly as a shipped parcel, so two drafts composed
 * together cannot both take the last unit; a cancelled shipment holds nothing.
 *
 * Only the lines that physically leave count: a service line (gift wrapping) and a
 * virtual product are never to ship, and are absent from what is returned.
 */
final readonly class ShippedQuantityCalculator
{
    /**
     * Below this, two FLOAT quantities are the same: `order_product.quantity` is a
     * FLOAT, and a sum of thirds compared exactly would leave a hundredth to ship.
     */
    public const float TOLERANCE = 0.0001;

    /**
     * The quantity held by the shipments of the order, by order line id.
     *
     * @return array<int, float>
     */
    public function heldByOrderProduct(Order $order, ?ConnectionInterface $connection = null): array
    {
        return $this->sumByOrderProduct($order, false, $connection);
    }

    /**
     * The quantity that already left in a shipped parcel, by order line id. A draft
     * holds its lines but has not left.
     *
     * @return array<int, float>
     */
    public function shippedByOrderProduct(Order $order, ?ConnectionInterface $connection = null): array
    {
        return $this->sumByOrderProduct($order, true, $connection);
    }

    /**
     * What is left to ship on each shippable line of the order, by order line id,
     * zero included: a line absent from the result is not a line that ships.
     *
     * @return array<int, float>
     */
    public function remainingByOrderProduct(Order $order, ?ConnectionInterface $connection = null): array
    {
        $held = $this->heldByOrderProduct($order, $connection);
        $remaining = [];

        foreach ($this->shippableLines($order, $connection) as $line) {
            $lineId = (int) $line->getId();
            $left = (float) $line->getQuantity() - ($held[$lineId] ?? 0.0);

            $remaining[$lineId] = $left > self::TOLERANCE ? $left : 0.0;
        }

        return $remaining;
    }

    /**
     * Whether every shippable line of the order left in a shipped parcel, and at least
     * one did. A draft still open holds its lines without having left: an order whose
     * last lines wait in a draft is not fully shipped.
     */
    public function isFullyShipped(Order $order, ?ConnectionInterface $connection = null): bool
    {
        $lines = $this->shippableLines($order, $connection);

        if ([] === $lines) {
            return false;
        }

        $shipped = $this->shippedByOrderProduct($order, $connection);

        foreach ($lines as $line) {
            if ((float) $line->getQuantity() - ($shipped[(int) $line->getId()] ?? 0.0) > self::TOLERANCE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sums the items of the order's shipments by order line id: every shipment but the
     * cancelled ones, or only the shipped ones.
     *
     * @return array<int, float>
     */
    private function sumByOrderProduct(Order $order, bool $shippedOnly, ?ConnectionInterface $connection): array
    {
        $connection ??= Propel::getConnection(OrderShipmentTableMap::DATABASE_NAME);

        $statement = $connection->prepare(
            'SELECT `item`.`order_product_id`, SUM(`item`.`quantity`) AS `held`'
            .' FROM `order_shipment_item` AS `item`'
            .' INNER JOIN `order_shipment` AS `shipment` ON `shipment`.`id` = `item`.`order_shipment_id`'
            .' WHERE `shipment`.`order_id` = :order_id AND `shipment`.`status` '.($shippedOnly ? '=' : '<>').' :status'
            .' GROUP BY `item`.`order_product_id`',
        );
        $statement->bindValue(':order_id', (int) $order->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':status', $shippedOnly ? OrderShipment::STATUS_SHIPPED : OrderShipment::STATUS_CANCELLED, \PDO::PARAM_STR);
        $statement->execute();

        $sums = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $sums[(int) $row['order_product_id']] = (float) $row['held'];
        }

        return $sums;
    }

    /**
     * @return list<OrderProduct>
     */
    private function shippableLines(Order $order, ?ConnectionInterface $connection): array
    {
        $lines = [];

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find($connection) as $line) {
            if ($line->isServiceLine() || 1 === (int) $line->getVirtual()) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
