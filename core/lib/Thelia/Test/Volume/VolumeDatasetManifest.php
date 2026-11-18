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
 * The marker of a volume dataset: the identifier range it wrote in every table that has an
 * identifier, kept in one meta_data row. The purge deletes those ranges and nothing else, so
 * the rows of the shop the dataset was generated in are never touched.
 */
final class VolumeDatasetManifest
{
    public const META_KEY = 'volume_dataset';

    public const ELEMENT_KEY = 'manifest';

    public const ELEMENT_ID = 1;

    /**
     * Prefix of the reference of every generated product, combination, customer and order: the
     * purge adds it to the identifier range of those tables, a second lock on the rows it deletes.
     */
    public const REF_PREFIX = 'VOL-';

    /** @var array<string, int> */
    private array $nextIds = [];

    /**
     * @param array<string, array{0: int, 1: int}>   $ranges  first and last identifier per table
     * @param array<string, int|string|list<string>> $options
     * @param array<string, float>                   $seconds
     * @param list<string>                           $files   media files written, relative to the media directory
     */
    public function __construct(
        private array $ranges = [],
        private array $options = [],
        private array $seconds = [],
        private array $files = [],
    ) {
    }

    /**
     * Identifiers are given by the dataset, not by the auto-increment, so that two generations on
     * the same starting database give the same identifiers, and so that a child row can be written
     * in the same batch as its parent.
     */
    public function nextId(ConnectionInterface $connection, string $table): int
    {
        if (!isset($this->nextIds[$table])) {
            $statement = $connection->prepare(\sprintf('SELECT COALESCE(MAX(id), 0) + 1 FROM `%s`', $table));
            $statement->execute();
            $this->nextIds[$table] = (int) $statement->fetchColumn();
        }

        $id = $this->nextIds[$table]++;
        $this->ranges[$table] = [$this->ranges[$table][0] ?? $id, $id];

        return $id;
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public function ranges(): array
    {
        return $this->ranges;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function range(string $table): ?array
    {
        return $this->ranges[$table] ?? null;
    }

    /**
     * @param array<string, int|string|list<string>> $options
     */
    public function recordOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return array<string, int|string|list<string>>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function recordDuration(string $step, float $seconds): void
    {
        $this->seconds[$step] = round($seconds, 3);
    }

    /**
     * @return array<string, float>
     */
    public function durations(): array
    {
        return $this->seconds;
    }

    public function recordFile(string $relativePath): void
    {
        if (!\in_array($relativePath, $this->files, true)) {
            $this->files[] = $relativePath;
        }
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function toJson(): string
    {
        return json_encode(
            ['options' => $this->options, 'ranges' => $this->ranges, 'seconds' => $this->seconds, 'files' => $this->files],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );
    }

    public static function fromJson(string $json): self
    {
        /** @var array{options?: array<string, int|string|list<string>>, ranges?: array<string, array{0: int, 1: int}>, seconds?: array<string, float>, files?: list<string>} $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return new self($data['ranges'] ?? [], $data['options'] ?? [], $data['seconds'] ?? [], $data['files'] ?? []);
    }

    public static function load(ConnectionInterface $connection): ?self
    {
        $statement = $connection->prepare('SELECT value FROM meta_data WHERE meta_key = ? AND element_key = ? AND element_id = ?');
        $statement->execute([self::META_KEY, self::ELEMENT_KEY, self::ELEMENT_ID]);
        // fetch() rather than fetchColumn(): Propel's statement wrapper gives an empty string, not
        // false, when there is no row.
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) && \is_string($row[0]) ? self::fromJson($row[0]) : null;
    }

    public function save(ConnectionInterface $connection, string $date): void
    {
        $connection->prepare('DELETE FROM meta_data WHERE meta_key = ? AND element_key = ? AND element_id = ?')
            ->execute([self::META_KEY, self::ELEMENT_KEY, self::ELEMENT_ID]);
        $connection->prepare('INSERT INTO meta_data (meta_key, element_key, element_id, is_serialized, value, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?, ?)')
            ->execute([self::META_KEY, self::ELEMENT_KEY, self::ELEMENT_ID, $this->toJson(), $date, $date]);
    }

    public static function delete(ConnectionInterface $connection): void
    {
        $connection->prepare('DELETE FROM meta_data WHERE meta_key = ? AND element_key = ? AND element_id = ?')
            ->execute([self::META_KEY, self::ELEMENT_KEY, self::ELEMENT_ID]);
    }
}
