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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Checkout\Exception\DeliverySlotFullException;

/**
 * Counts the orders that hold a slot on a day, in `delivery_slot_booking`.
 *
 * The place is taken by a single conditional UPDATE, the way StockDecrementer takes stock:
 * the check and the write are one statement, so two orders racing for the last place are
 * arbitrated by the database and only one of them gets it. A count of the orders read
 * beforehand would let both through.
 */
final readonly class DeliverySlotBooker
{
    /**
     * Takes a place in the slot on that day, inside the transaction of the order.
     *
     * @throws DeliverySlotFullException when the slot has no place left that day
     * @throws \PDOException             when the database gives up waiting for an order holding the same place
     */
    public function book(int $slotId, string $date, ConnectionInterface $connection): void
    {
        $this->ensureCounter($slotId, $date, $connection);

        $statement = $connection->prepare(
            'UPDATE `delivery_slot_booking` `booking`
                INNER JOIN `delivery_slot` `slot` ON `slot`.`id` = `booking`.`delivery_slot_id`
                SET `booking`.`booked` = `booking`.`booked` + 1
                WHERE `booking`.`delivery_slot_id` = :slot AND `booking`.`delivery_date` = :date
                  AND (`slot`.`capacity` IS NULL OR `booking`.`booked` < `slot`.`capacity`)',
        );
        $statement->bindValue(':slot', $slotId, \PDO::PARAM_INT);
        $statement->bindValue(':date', $date);
        $statement->execute();

        if (0 === $statement->rowCount()) {
            throw new DeliverySlotFullException();
        }
    }

    /**
     * Gives the place back, when the order stops holding it. Never below zero: a counter
     * written before a slot was given a capacity, or edited by hand, is not made negative.
     */
    public function release(int $slotId, string $date, ConnectionInterface $connection): void
    {
        $statement = $connection->prepare(
            'UPDATE `delivery_slot_booking` SET `booked` = `booked` - 1
                WHERE `delivery_slot_id` = :slot AND `delivery_date` = :date AND `booked` > 0',
        );
        $statement->bindValue(':slot', $slotId, \PDO::PARAM_INT);
        $statement->bindValue(':date', $date);
        $statement->execute();
    }

    /**
     * Takes the place back for an order that holds the slot again, whatever the capacity
     * says: the order already has this day, and the count has to say so even if it goes over.
     */
    public function restore(int $slotId, string $date, ConnectionInterface $connection): void
    {
        $this->ensureCounter($slotId, $date, $connection);

        $statement = $connection->prepare(
            'UPDATE `delivery_slot_booking` SET `booked` = `booked` + 1
                WHERE `delivery_slot_id` = :slot AND `delivery_date` = :date',
        );
        $statement->bindValue(':slot', $slotId, \PDO::PARAM_INT);
        $statement->bindValue(':date', $date);
        $statement->execute();
    }

    /**
     * The counter row of the day, created at zero the first time it is needed. INSERT IGNORE
     * on the unique key: a concurrent order creating the same row is not an error.
     */
    private function ensureCounter(int $slotId, string $date, ConnectionInterface $connection): void
    {
        $statement = $connection->prepare(
            'INSERT IGNORE INTO `delivery_slot_booking` (`delivery_slot_id`, `delivery_date`, `booked`) VALUES (:slot, :date, 0)',
        );
        $statement->bindValue(':slot', $slotId, \PDO::PARAM_INT);
        $statement->bindValue(':date', $date);
        $statement->execute();
    }
}
