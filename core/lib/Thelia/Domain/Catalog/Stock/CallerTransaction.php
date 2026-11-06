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
 * A caller holds a transaction open whenever the connection is in one: a hold of the stock
 * never runs inside it, and a change of stock written inside it is announced once it is over.
 */
final readonly class CallerTransaction implements CallerTransactionInterface
{
    public function isOpen(ConnectionInterface $connection): bool
    {
        return $connection->inTransaction();
    }
}
