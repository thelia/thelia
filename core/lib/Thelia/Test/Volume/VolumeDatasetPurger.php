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
use Symfony\Component\Filesystem\Filesystem;

/**
 * Deletes a volume dataset: the identifier ranges its manifest recorded, table by table, children
 * first, then its image files and its manifest. A row of the shop is never selected: every
 * condition is a range the dataset wrote, and the products, combinations, customers and orders
 * must also carry the dataset reference prefix.
 */
final readonly class VolumeDatasetPurger
{
    private const DELETE_CHUNK = 5000;

    public function __construct(
        private ConnectionInterface $connection,
        private string $mediaDirectory,
    ) {
    }

    /**
     * @return array<string, int> rows the purge selects, per table
     */
    public function count(): array
    {
        $manifest = $this->manifest();
        $counts = [];
        foreach ($this->conditions($manifest) as $table => [$where, $parameters]) {
            $statement = $this->connection->prepare(\sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, $where));
            $statement->execute($parameters);
            $counts[$table] = ($counts[$table] ?? 0) + (int) $statement->fetchColumn();
        }

        return $counts;
    }

    /**
     * @param (callable(string, int): void)|null $progress called with a table and the rows deleted from it
     *
     * @return array<string, int> rows deleted, per table
     */
    public function purge(?callable $progress = null): array
    {
        $manifest = $this->manifest();
        $deleted = [];
        foreach ($this->conditions($manifest) as $table => [$where, $parameters]) {
            $sql = \sprintf('DELETE FROM `%s` WHERE %s LIMIT %d', $table, $where, self::DELETE_CHUNK);
            do {
                $statement = $this->connection->prepare($sql);
                $statement->execute($parameters);
                $rows = $statement->rowCount();
                $deleted[$table] = ($deleted[$table] ?? 0) + $rows;
            } while (self::DELETE_CHUNK === $rows);

            if (null !== $progress) {
                $progress($table, $deleted[$table]);
            }
        }

        $filesystem = new Filesystem();
        foreach ($manifest->files() as $file) {
            $filesystem->remove(rtrim($this->mediaDirectory, '/').'/'.$file);
        }

        VolumeDatasetManifest::delete($this->connection);

        return $deleted;
    }

    private function manifest(): VolumeDatasetManifest
    {
        return VolumeDatasetManifest::load($this->connection)
            ?? throw new \RuntimeException('No volume dataset in this database.');
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<int|string>}>
     */
    private function conditions(VolumeDatasetManifest $manifest): \Generator
    {
        foreach (array_keys(VolumeDatasetTables::TABLES) as $table) {
            $condition = VolumeDatasetTables::condition($manifest, $table);
            if (null !== $condition) {
                yield $table => $condition;
            }

            // An administrator who renames a generated product or category adds a URL outside the
            // range the dataset wrote: it points at a row about to disappear, so it goes too.
            if ('rewriting_url' === $table) {
                $own = $manifest->range('rewriting_url') ?? [0, -1];
                foreach (['product', 'category'] as $view) {
                    $range = $manifest->range($view);
                    if (null !== $range) {
                        yield 'rewriting_url' => [
                            '`view` = ? AND CAST(`view_id` AS UNSIGNED) BETWEEN ? AND ? AND `id` NOT BETWEEN ? AND ?',
                            [$view, $range[0], $range[1], $own[0], $own[1]],
                        ];
                    }
                }
            }
        }
    }
}
