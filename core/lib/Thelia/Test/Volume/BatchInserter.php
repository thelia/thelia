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
 * Writes rows with multi-row INSERT statements, one transaction per batch.
 *
 * Propel saves one object per statement and has no unit of work: a hundred thousand
 * products saved one by one take hours. A volume dataset is written table by table,
 * a batch of rows per statement, which is what makes its generation time worth reporting.
 */
final class BatchInserter
{
    public const DEFAULT_BATCH_SIZE = 500;

    /** @var array<string, int> */
    private array $writtenRows = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('A batch holds at least one row.');
        }
    }

    /**
     * @param list<string>                          $columns
     * @param iterable<list<string|int|float|null>> $rows
     */
    public function insert(string $table, array $columns, iterable $rows): int
    {
        $count = 0;
        foreach ($this->batches($rows) as $batch) {
            $this->writeBatch($table, $columns, $batch);
            $count += \count($batch);
        }

        $this->writtenRows[$table] = ($this->writtenRows[$table] ?? 0) + $count;

        return $count;
    }

    /**
     * @return array<string, int>
     */
    public function writtenRows(): array
    {
        return $this->writtenRows;
    }

    /**
     * @param iterable<list<string|int|float|null>> $rows
     *
     * @return \Generator<int, list<list<string|int|float|null>>>
     */
    public function batches(iterable $rows): \Generator
    {
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = $row;
            if (\count($batch) === $this->batchSize) {
                yield $batch;
                $batch = [];
            }
        }

        if ([] !== $batch) {
            yield $batch;
        }
    }

    /**
     * @param list<string>                      $columns
     * @param list<list<string|int|float|null>> $batch
     */
    private function writeBatch(string $table, array $columns, array $batch): void
    {
        $columnCount = \count($columns);
        $placeholders = '('.implode(',', array_fill(0, $columnCount, '?')).')';
        $sql = \sprintf(
            'INSERT INTO `%s` (`%s`) VALUES %s',
            $table,
            implode('`,`', $columns),
            implode(',', array_fill(0, \count($batch), $placeholders)),
        );

        $parameters = [];
        foreach ($batch as $row) {
            if (\count($row) !== $columnCount) {
                throw new \LogicException(\sprintf('A row of %s has %d values for %d columns.', $table, \count($row), $columnCount));
            }
            foreach ($row as $value) {
                $parameters[] = $value;
            }
        }

        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
