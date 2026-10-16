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
}
