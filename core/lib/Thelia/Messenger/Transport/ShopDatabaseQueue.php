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

use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * One queue of the shop database, read in SQL where the transport only offers to
 * decode every job it holds: listing the newest first, taking one job out once,
 * deleting by age.
 *
 * Only for a DSN that names the shop database; any other queue goes through its
 * transport.
 */
final readonly class ShopDatabaseQueue
{
    private function __construct(
        private ShopDatabaseConnection $connection,
        private string $table,
        private string $queueName,
        private int $redeliverTimeout,
    ) {
    }

    public static function of(ShopDatabaseConnection $connection, #[\SensitiveParameter] string $dsn): ?self
    {
        if (!str_starts_with($dsn, 'doctrine://')) {
            return null;
        }

        $configuration = Connection::buildConfiguration($dsn);

        if (ShopDatabaseTransportFactory::CONNECTION_NAME !== $configuration['connection']) {
            return null;
        }

        return new self($connection, (string) $configuration['table_name'], (string) $configuration['queue_name'], (int) $configuration['redeliver_timeout']);
    }

    /**
     * @return list<string> the ids of the last jobs queued, the newest first
     */
    public function newestIds(int $limit): array
    {
        return array_map('strval', $this->connection->get()->fetchFirstColumn(
            'SELECT id FROM '.$this->quotedTable().' WHERE queue_name = ? ORDER BY id DESC LIMIT '.max(0, $limit),
            [$this->queueName],
        ));
    }

    /**
     * Takes one job out of the queue, if it is still there and no worker holds it.
     *
     * A worker, `messenger:failed:retry` among them, holds the job it received until
     * its redeliver timeout: taken from under it, the job would be sent twice.
     *
     * @return bool false when another process took it first, or holds it
     */
    public function take(string $id): bool
    {
        // The transport writes its dates in UTC.
        $heldSince = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(\sprintf('-%d seconds', $this->redeliverTimeout));

        return 1 === (int) $this->connection->get()->executeStatement(
            'DELETE FROM '.$this->quotedTable().' WHERE id = ? AND queue_name = ? AND (delivered_at IS NULL OR delivered_at < ?)',
            [$id, $this->queueName, $heldSince->format('Y-m-d H:i:s')],
        );
    }

    /**
     * Deletes the jobs queued before $limit, or counts them.
     */
    public function deleteQueuedBefore(\DateTimeImmutable $limit, bool $dryRun = false): int
    {
        // The transport writes its dates in UTC.
        $parameters = [$this->queueName, $limit->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $where = ' FROM '.$this->quotedTable().' WHERE queue_name = ? AND created_at < ?';

        return $dryRun
            ? (int) $this->connection->get()->fetchOne('SELECT COUNT(*)'.$where, $parameters)
            : (int) $this->connection->get()->executeStatement('DELETE'.$where, $parameters);
    }

    private function quotedTable(): string
    {
        return $this->connection->get()->quoteSingleIdentifier($this->table);
    }
}
