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
use Thelia\Exception\TheliaProcessException;

/**
 * Decrements product sale elements stock with a single conditional UPDATE,
 * letting the database arbitrate concurrent checkouts. This closes the
 * read-check-write race of the historical flow, where two simultaneous
 * orders could both pass the availability check and silently oversell.
 */
readonly class StockDecrementer
{
    /**
     * @param bool $guardAvailability when true (and negative stock is not allowed), an
     *                                insufficient stock aborts with a TheliaProcessException
     *                                instead of silently clamping to zero
     *
     * @throws TheliaProcessException
     */
    public function decrement(
        int $productSaleElementsId,
        float $quantity,
        bool $guardAvailability,
        bool $allowNegativeStock,
        ConnectionInterface $connection,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        if ($allowNegativeStock) {
            $sql = 'UPDATE `product_sale_elements` SET `quantity` = `quantity` - :quantity WHERE `id` = :id';
        } elseif ($guardAvailability) {
            $sql = 'UPDATE `product_sale_elements` SET `quantity` = `quantity` - :quantity WHERE `id` = :id AND `quantity` >= :quantity';
        } else {
            // Historical behaviour when availability is not checked: never
            // fail, floor the stock at zero.
            $sql = 'UPDATE `product_sale_elements` SET `quantity` = GREATEST(`quantity` - :quantity, 0) WHERE `id` = :id';
        }

        $statement = $connection->prepare($sql);
        $statement->bindValue(':quantity', $quantity);
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);
        $statement->execute();

        if ($guardAvailability && !$allowNegativeStock && 0 === $statement->rowCount()) {
            throw new TheliaProcessException('Not enough stock');
        }
    }
}
