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
use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderTableMap;

readonly class OrderTransactionManager
{
    public function begin(): ConnectionInterface
    {
        $connection = Propel::getConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        return $connection;
    }

    public function commit(ConnectionInterface $connection): void
    {
        $connection->commit();
    }

    public function rollback(ConnectionInterface $connection): void
    {
        $connection->rollBack();
    }
}
