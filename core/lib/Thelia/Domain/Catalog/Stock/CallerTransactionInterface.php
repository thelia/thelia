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

/**
 * Whether a caller has a transaction open on the connection a hold or an announcement of the
 * stock would use (StockReservationService, StockLevelAnnouncer).
 */
interface CallerTransactionInterface
{
    public function isOpen(ConnectionInterface $connection): bool;
}
