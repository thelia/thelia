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

namespace Thelia\Messenger\Transport;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * A second connection to the shop database, beside the Propel one, opened on first use.
 *
 * The queue reads and writes through it, and so does the bookkeeping of a job that
 * must be seen while the job itself works inside a transaction: what goes through
 * here is committed at once, whatever the Propel connection is in the middle of.
 */
final class ShopDatabaseConnection
{
    private ?Connection $connection = null;

    public function get(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(PdoDsnReader::connectionParameters());
    }

    /**
     * Lets the connection go; the next query opens a new one. A worker does it after
     * every job: a long one leaves the connection idle past the wait_timeout of the
     * server, and the job would otherwise be acknowledged on a connection the server
     * has closed, then handed again as if it had never run.
     */
    public function close(): void
    {
        $this->connection?->close();
    }
}
