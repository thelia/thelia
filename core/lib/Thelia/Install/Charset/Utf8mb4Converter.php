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

namespace Thelia\Install\Charset;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Brings the tables of a database created in utf8 (utf8mb3), as a shop upgraded from
 * Thelia 2 has them, to the utf8mb4 character set a fresh install uses.
 *
 * The connection talks utf8mb4, so a character outside the Basic Multilingual Plane (an
 * emoji, some CJK ideographs) reaches the server intact and a utf8mb3 column refuses it
 * with error 1366. utf8mb3 is a subset of utf8mb4 with the same bytes, so the conversion
 * never rewrites a stored value; it still rebuilds each table.
 *
 * Only utf8 / utf8mb3 tables are converted. A table holding another character set (latin1
 * mostly) is reported and left alone: its bytes may be UTF-8 written through a latin1
 * connection, and converting them would garble every accented character.
 */
final class Utf8mb4Converter
{
    public const CHARSET = 'utf8mb4';
    public const COLLATION = 'utf8mb4_general_ci';

    private const CONVERTIBLE_CHARSETS = ['utf8', 'utf8mb3'];

    private const BYTES_PER_CHARACTER = 4;

    /**
     * Longest index key each engine accepts, in bytes. An InnoDB table is moved to the
     * DYNAMIC row format when it is not in it, so the 767 bytes of COMPACT never apply.
     */
    private const MAXIMUM_KEY_LENGTH = [
        'innodb' => 3072,
        'myisam' => 1000,
    ];

    /**
     * CONVERT TO widens TEXT to MEDIUMTEXT and MEDIUMTEXT to LONGTEXT so the column keeps the
     * number of characters it could hold in utf8mb3. The stored bytes do not change, and the
     * fresh install declares these columns TEXT, so each keeps its type: MariaDB honours a
     * MODIFY given in the same statement, MySQL widens anyway and needs a second statement.
     */
    private const TYPES_CONVERT_TO_WIDENS = ['tinytext', 'text', 'mediumtext'];

    private ?bool $mariaDb = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function databaseCharset(): string
    {
        return (string) $this->fetchValue(
            'SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()',
        );
    }

    /**
     * @param list<string> $onlyTables restrict the inspection to these tables; every table when empty
     *
     * @return list<Utf8mb4TableConversion> the tables that are not entirely in utf8mb4, by name
     */
    public function tablesToConvert(array $onlyTables = []): array
    {
        $tables = $this->baseTables();

        $unknownTables = array_diff($onlyTables, array_keys($tables));

        if ([] !== $unknownTables) {
            throw new \InvalidArgumentException(\sprintf('No table named %s in this database.', implode(', ', array_map(static fn (string $table): string => '"'.$table.'"', $unknownTables))));
        }

        if ([] !== $onlyTables) {
            $tables = array_intersect_key($tables, array_flip($onlyTables));
        }

        $columnsByTable = $this->characterColumns();
        $indexesByTable = $this->indexes();
        $foreignKeys = $this->foreignKeys();

        $conversions = [];

        foreach ($tables as $name => $table) {
            $columns = $columnsByTable[$name] ?? [];
            $tableCharset = $this->charsetOfCollation($table['collation']);

            $columnsToConvert = array_filter($columns, static fn (CharacterColumn $column): bool => !$column->isUtf8mb4());

            if (self::CHARSET === $tableCharset && [] === $columnsToConvert) {
                continue;
            }

            $conversions[] = new Utf8mb4TableConversion(
                table: $name,
                engine: $table['engine'],
                rowFormat: $table['row_format'],
                collation: $table['collation'],
                columns: $columns,
                approximateRows: $table['rows'],
                sizeInBytes: $table['size'],
                blockers: [
                    ...$this->foreignKeyBlockers($name, $columnsToConvert, $foreignKeys),
                    ...$this->indexBlockers($table['engine'], $columns, $indexesByTable[$name] ?? []),
                ],
                unsupportedCharset: $this->unsupportedCharset($tableCharset, $columnsToConvert),
            );
        }

        return $conversions;
    }

    /**
     * Rebuilds the table in utf8mb4. The table is locked for writes for the duration.
     */
    public function convert(Utf8mb4TableConversion $conversion): void
    {
        if (!$conversion->isConvertible()) {
            throw new \LogicException(\sprintf('Table "%s" cannot be converted: %s', $conversion->table, implode('; ', $conversion->blockers) ?: 'it holds '.$conversion->unsupportedCharset.' data'));
        }

        $this->connection->exec($this->alterStatement($conversion));

        $widenedColumns = $this->widenedColumns($conversion);

        if ([] !== $widenedColumns) {
            $this->connection->exec(\sprintf(
                'ALTER TABLE %s %s',
                $this->quoteIdentifier($conversion->table),
                implode(', ', array_map(fn (CharacterColumn $column): string => 'MODIFY '.$this->columnDefinition($column), $widenedColumns)),
            ));
        }
    }

    /**
     * The character set a table created without naming one inherits: a module installed
     * after the conversion must not create its tables in utf8mb3 again.
     */
    public function convertDatabaseDefault(): void
    {
        $this->connection->exec(\sprintf('ALTER DATABASE CHARACTER SET %s COLLATE %s', self::CHARSET, self::COLLATION));
    }

    public function alterStatement(Utf8mb4TableConversion $conversion): string
    {
        $clauses = [\sprintf('CONVERT TO CHARACTER SET %s COLLATE %s', self::CHARSET, self::COLLATION)];

        if ($conversion->needsDynamicRowFormat()) {
            $clauses[] = 'ROW_FORMAT=DYNAMIC';
        }

        foreach ($conversion->columns as $column) {
            if ($this->keepsItsDefinition($column)) {
                $clauses[] = 'MODIFY '.$this->columnDefinition($column);
            }
        }

        return \sprintf('ALTER TABLE %s %s', $this->quoteIdentifier($conversion->table), implode(', ', $clauses));
    }

    /**
     * A column CONVERT TO would widen is redefined in the same statement with its own type.
     */
    private function keepsItsDefinition(CharacterColumn $column): bool
    {
        return !$column->isGenerated()
            && \in_array(strtolower($column->dataType), self::TYPES_CONVERT_TO_WIDENS, true);
    }

    /**
     * @return list<CharacterColumn> the columns, as they were before the conversion, whose type it changed
     */
    private function widenedColumns(Utf8mb4TableConversion $conversion): array
    {
        $typesAfterConversion = $this->characterColumns()[$conversion->table] ?? [];

        return array_values(array_filter(
            $conversion->columns,
            fn (CharacterColumn $column): bool => $this->keepsItsDefinition($column)
                && isset($typesAfterConversion[$column->name])
                && strtolower($typesAfterConversion[$column->name]->columnType) !== strtolower($column->columnType),
        ));
    }

    private function columnDefinition(CharacterColumn $column): string
    {
        return \sprintf(
            '%s %s CHARACTER SET %s COLLATE %s %s%s%s%s',
            $this->quoteIdentifier($column->name),
            $column->columnType,
            self::CHARSET,
            self::COLLATION,
            $column->nullable ? 'NULL' : 'NOT NULL',
            $this->defaultClause($column),
            $column->isInvisible() ? ' INVISIBLE' : '',
            '' !== $column->comment ? ' COMMENT '.$this->connection->quote($column->comment) : '',
        );
    }

    /**
     * MariaDB reports a default as the SQL expression that produces it (a literal comes
     * quoted, and "NULL" means no default). MySQL reports the bare value, and flags a default
     * given as an expression, the only kind a TEXT column accepts there, with DEFAULT_GENERATED;
     * it writes that expression with its quotes escaped (_utf8mb4\'none\'), which SQL does not read.
     */
    private function defaultClause(CharacterColumn $column): string
    {
        if (null === $column->default) {
            return '';
        }

        if ($this->isMariaDb()) {
            return 'NULL' === $column->default ? '' : ' DEFAULT '.$column->default;
        }

        if (str_contains(strtoupper($column->extra), 'DEFAULT_GENERATED')) {
            return ' DEFAULT ('.str_replace("\\'", "'", $column->default).')';
        }

        return ' DEFAULT '.$this->connection->quote($column->default);
    }

    /**
     * @param array<string, CharacterColumn> $columnsToConvert
     */
    private function unsupportedCharset(string $tableCharset, array $columnsToConvert): ?string
    {
        $charsets = array_unique([
            ...(self::CHARSET === $tableCharset ? [] : [$tableCharset]),
            ...array_map(static fn (CharacterColumn $column): string => $column->charset, array_values($columnsToConvert)),
        ]);

        $unsupported = array_diff($charsets, self::CONVERTIBLE_CHARSETS);

        return [] === $unsupported ? null : implode(', ', $unsupported);
    }

    /**
     * MariaDB and MySQL refuse to change the character set of a column a foreign key uses,
     * foreign_key_checks off or not (errors 1832 and 1833).
     *
     * @param array<string, CharacterColumn>                                                                                      $columnsToConvert
     * @param list<array{table: string, column: string, constraint: string, referenced_table: string, referenced_column: string}> $foreignKeys
     *
     * @return list<string>
     */
    private function foreignKeyBlockers(string $table, array $columnsToConvert, array $foreignKeys): array
    {
        $blockers = [];

        foreach ($foreignKeys as $foreignKey) {
            $column = match ($table) {
                $foreignKey['table'] => $foreignKey['column'],
                $foreignKey['referenced_table'] => $foreignKey['referenced_column'],
                default => null,
            };

            if (null === $column || !isset($columnsToConvert[$column])) {
                continue;
            }

            $blockers[] = \sprintf(
                'column "%s" belongs to the foreign key "%s" (%s.%s -> %s.%s): drop the key, convert both tables, then add it back',
                $column,
                $foreignKey['constraint'],
                $foreignKey['table'],
                $foreignKey['column'],
                $foreignKey['referenced_table'],
                $foreignKey['referenced_column'],
            );
        }

        return $blockers;
    }

    /**
     * @param array<string, CharacterColumn>                                           $columns
     * @param array<string, list<array{column: string, sub_part: ?int, type: string}>> $indexes
     *
     * @return list<string>
     */
    private function indexBlockers(string $engine, array $columns, array $indexes): array
    {
        $maximumKeyLength = self::MAXIMUM_KEY_LENGTH[strtolower($engine)] ?? null;

        if (null === $maximumKeyLength) {
            return [];
        }

        $blockers = [];

        foreach ($indexes as $index => $parts) {
            $keyLength = 0;

            foreach ($parts as $part) {
                if (\in_array($part['type'], ['FULLTEXT', 'SPATIAL'], true) || !isset($columns[$part['column']])) {
                    continue;
                }

                $keyLength += ($part['sub_part'] ?? $columns[$part['column']]->maximumLength) * self::BYTES_PER_CHARACTER;
            }

            if ($keyLength > $maximumKeyLength) {
                $blockers[] = \sprintf(
                    'index "%s" would take %d bytes in utf8mb4, over the %d bytes %s accepts: shorten it with a prefix length%s',
                    $index,
                    $keyLength,
                    $maximumKeyLength,
                    $engine,
                    'innodb' === strtolower($engine) ? '' : ', or move the table to InnoDB',
                );
            }
        }

        return $blockers;
    }

    private function charsetOfCollation(string $collation): string
    {
        return explode('_', $collation, 2)[0];
    }

    /**
     * @return array<string, array{engine: string, row_format: string, collation: string, rows: int, size: int}>
     */
    private function baseTables(): array
    {
        $tables = [];

        foreach ($this->fetchAll(
            "SELECT TABLE_NAME AS table_name, ENGINE AS engine, ROW_FORMAT AS row_format, TABLE_COLLATION AS table_collation,
                    TABLE_ROWS AS table_rows, DATA_LENGTH + INDEX_LENGTH AS size
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME",
        ) as $row) {
            $tables[(string) $row['table_name']] = [
                'engine' => (string) $row['engine'],
                'row_format' => (string) $row['row_format'],
                'collation' => (string) $row['table_collation'],
                'rows' => (int) $row['table_rows'],
                'size' => (int) $row['size'],
            ];
        }

        return $tables;
    }

    /**
     * @return array<string, array<string, CharacterColumn>>
     */
    private function characterColumns(): array
    {
        $columns = [];

        foreach ($this->fetchAll(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type,
                    CHARACTER_SET_NAME AS charset, COLLATION_NAME AS collation, IS_NULLABLE AS nullable,
                    COLUMN_DEFAULT AS column_default, EXTRA AS extra, COLUMN_COMMENT AS column_comment,
                    CHARACTER_MAXIMUM_LENGTH AS maximum_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
        ) as $row) {
            $columns[(string) $row['table_name']][(string) $row['column_name']] = new CharacterColumn(
                name: (string) $row['column_name'],
                dataType: (string) $row['data_type'],
                columnType: (string) $row['column_type'],
                charset: (string) $row['charset'],
                collation: (string) $row['collation'],
                nullable: 'YES' === $row['nullable'],
                default: null === $row['column_default'] ? null : (string) $row['column_default'],
                extra: (string) $row['extra'],
                comment: (string) $row['column_comment'],
                maximumLength: (int) $row['maximum_length'],
            );
        }

        return $columns;
    }

    /**
     * @return array<string, array<string, list<array{column: string, sub_part: ?int, type: string}>>>
     */
    private function indexes(): array
    {
        $indexes = [];

        foreach ($this->fetchAll(
            'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, COLUMN_NAME AS column_name,
                    SUB_PART AS sub_part, INDEX_TYPE AS index_type
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
        ) as $row) {
            $indexes[(string) $row['table_name']][(string) $row['index_name']][] = [
                'column' => (string) $row['column_name'],
                'sub_part' => null === $row['sub_part'] ? null : (int) $row['sub_part'],
                'type' => strtoupper((string) $row['index_type']),
            ];
        }

        return $indexes;
    }

    /**
     * @return list<array{table: string, column: string, constraint: string, referenced_table: string, referenced_column: string}>
     */
    private function foreignKeys(): array
    {
        return array_map(
            static fn (array $row): array => [
                'table' => (string) $row['table_name'],
                'column' => (string) $row['column_name'],
                'constraint' => (string) $row['constraint_name'],
                'referenced_table' => (string) $row['referenced_table_name'],
                'referenced_column' => (string) $row['referenced_column_name'],
            ],
            $this->fetchAll(
                'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, CONSTRAINT_NAME AS constraint_name,
                        REFERENCED_TABLE_NAME AS referenced_table_name, REFERENCED_COLUMN_NAME AS referenced_column_name
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
            ),
        );
    }

    private function isMariaDb(): bool
    {
        return $this->mariaDb ??= str_contains(strtolower((string) $this->fetchValue('SELECT VERSION()')), 'mariadb');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchValue(string $sql): mixed
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute();

        return $statement->fetchColumn();
    }
}
