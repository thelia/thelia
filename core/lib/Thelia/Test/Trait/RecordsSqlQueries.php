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

namespace Thelia\Test\Trait;

use Propel\Runtime\Connection\ConnectionWrapper;
use Psr\Log\AbstractLogger;

/**
 * Records the SQL statements a piece of code makes the database run.
 *
 * A test asserting on that list measures the shape of the work, not its wall
 * time: a page that reads one row per product costs one query more per product
 * added to the catalogue, and no timing threshold says that out loud.
 */
trait RecordsSqlQueries
{
    /**
     * @return list<string> the statements the Thelia connection ran while $work executed
     */
    protected function recordSqlQueries(callable $work): array
    {
        $connection = $this->getPropelConnection();

        if (!$connection instanceof ConnectionWrapper) {
            self::fail('The Thelia connection does not expose Propel\'s query log.');
        }

        $collector = new class extends AbstractLogger {
            /** @var list<string> */
            public array $statements = [];

            public function log($level, $message, array $context = []): void
            {
                $this->statements[] = (string) $message;
            }
        };

        $wasInDebugMode = $connection->isInDebugMode();
        $connection->setLogger($collector);
        $connection->useDebug(true);

        try {
            $work();
        } finally {
            $connection->useDebug($wasInDebugMode);
        }

        return $collector->statements;
    }

    /**
     * Counts the statements reading a table on their own, which is what a
     * relation loaded row by row looks like. A statement joining that table
     * to another one does not match: it is a single read, not a series.
     *
     * @param list<string> $statements
     */
    protected static function countSqlQueriesSelectingFrom(array $statements, string $table): int
    {
        return \count(
            array_filter(
                $statements,
                static fn (string $statement): bool => str_contains($statement, 'FROM `'.$table.'`'),
            ),
        );
    }
}
