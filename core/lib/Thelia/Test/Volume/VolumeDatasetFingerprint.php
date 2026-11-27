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

namespace Thelia\Test\Volume;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * A digest of every row a volume dataset wrote, table by table in a fixed order, each table sorted
 * by its key. Two datasets with the same digest hold the same rows: it is how two generations with
 * the same options are shown to be identical, and how a report names the dataset it measured. The
 * digest of each table says which tables differ when two datasets do.
 */
final readonly class VolumeDatasetFingerprint
{
    /**
     * @param array<string, int>    $rows   rows read, per table
     * @param array<string, string> $tables digest of the rows of each table
     */
    public function __construct(
        public string $digest,
        public array $rows,
        public array $tables = [],
    ) {
    }

    public static function of(ConnectionInterface $connection): self
    {
        $manifest = VolumeDatasetManifest::load($connection)
            ?? throw new \RuntimeException('No volume dataset in this database.');

        $context = hash_init('sha256');
        $rows = [];
        $tables = [];
        foreach (VolumeDatasetTables::TABLES as $table => [, , $orderBy]) {
            $condition = VolumeDatasetTables::condition($manifest, $table);
            if (null === $condition) {
                continue;
            }

            $statement = $connection->prepare(\sprintf('SELECT * FROM `%s` WHERE %s ORDER BY %s', $table, $condition[0], $orderBy));
            $statement->execute($condition[1]);
            $count = 0;
            $tableContext = hash_init('sha256');
            hash_update($context, $table."\n");
            while (false !== $row = $statement->fetch(\PDO::FETCH_NUM)) {
                $line = json_encode($row, \JSON_THROW_ON_ERROR)."\n";
                hash_update($context, $line);
                hash_update($tableContext, $line);
                ++$count;
            }
            $rows[$table] = $count;
            $tables[$table] = hash_final($tableContext);
        }

        return new self(hash_final($context), $rows, $tables);
    }
}
