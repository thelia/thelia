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

/**
 * Puts returned quantities back into the product sale elements stock with a
 * single atomic UPDATE, mirroring StockDecrementer so concurrent returns and
 * checkouts stay consistent without a read-modify-write race.
 */
readonly class StockIncrementer
{
    public function increment(
        int $productSaleElementsId,
        float $quantity,
        ConnectionInterface $connection,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $statement = $connection->prepare(
            'UPDATE `product_sale_elements` SET `quantity` = `quantity` + :quantity WHERE `id` = :id'
        );
        $statement->bindValue(':quantity', $quantity);
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
