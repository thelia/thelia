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

namespace Thelia\Core\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Thelia\Log\Tlog;

/**
 * Class Database.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Database
{
    /** The statement backupDb() closes a dump with, and restoreDb() looks for to tell a whole file from a truncated one. */
    private const DUMP_TERMINATOR = 'SET foreign_key_checks=1;';

    /**
     * The most a single INSERT of the dump carries (a row longer than that gets one of
     * its own). A quarter of the 4 MiB max_allowed_packet MySQL 5.7 defaults to.
     */
    private const INSERT_BATCH_BYTES = 1024 * 1024;

    protected ConnectionInterface|\PDO $connection;

    /**
     * Create a new instance, using the provided connection information, either none for
     * automatically a connection, a ConnectionWrapper instance (through ConnectionInterface) or a PDO connection.
     *
     * @param ConnectionInterface|\PDO|null $connection the connection object
     *
     * @throws \InvalidArgumentException if $connection is not of the suitable type
     */
    public function __construct(ConnectionInterface|\PDO|null $connection = null)
    {
        // Get a connection from Propel if we don't have one
        if (null === $connection) {
            $connection = Propel::getConnection(ServiceContainerInterface::CONNECTION_WRITE);
        }

        // Get the PDO connection from an
        if ($connection instanceof ConnectionWrapper) {
            $connection = $connection->getWrappedConnection();
        }

        if (!$connection instanceof \PDO && !$connection instanceof ConnectionInterface) {
            throw new \InvalidArgumentException('A PDO connection should be provided');
        }

        $this->connection = $connection;
    }

    /**
     * Insert all sql needed in database
     * Default insert /install/thelia.sql and /install/insert.sql.
     *
     * @param string $dbName        Database name
     * @param array  $extraSqlFiles SQL Files uri to insert
     */
    public function insertSql(?string $dbName = null, ?array $extraSqlFiles = null): void
    {
        if ($dbName) {
            $this->connection->query(\sprintf('use `%s`', $dbName));
        }

        $sql = [];

        if (null === $extraSqlFiles) {
            $sql = array_merge(
                $sql,
                $this->prepareSql(file_get_contents(THELIA_SETUP_DIRECTORY.'thelia.sql')),
                $this->prepareSql(file_get_contents(THELIA_SETUP_DIRECTORY.'insert.sql')),
            );
        } else {
            foreach ($extraSqlFiles as $fileToInsert) {
                $sql = array_merge(
                    $sql,
                    $this->prepareSql(file_get_contents($fileToInsert)),
                );
            }
        }

        foreach ($sql as $iValue) {
            if (!empty($iValue)) {
                $this->execute($iValue);
            }
        }
    }

    /**
     * A simple wrapper around PDO::exec.
     *
     * @param string $sql  SQL query
     * @param array  $args SQL request parameters (PDO style)
     *
     * @throws \RuntimeException|\PDOException if something goes wrong
     */
    public function execute(string $sql, array $args = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);

        if (false === $stmt) {
            throw new \RuntimeException(\sprintf('Failed to prepare statement for %s: ', $sql).print_r($this->connection->errorInfo(), true));
        }

        $success = $stmt->execute($args);
        if (false === $success || '00000' !== $stmt->errorCode()) {
            throw new \RuntimeException(\sprintf("Failed to execute SQL '%s', arguments:", $sql).print_r($args, true).', error:'.print_r($stmt->errorInfo(), true));
        }

        return $stmt;
    }

    /**
     * Separate each sql instruction in an array.
     */
    protected function prepareSql($sql): array
    {
        // Both substitutions below hide a semicolon from the splitter and put it back
        // once the file is cut. The tokens are drawn per call and checked against the
        // file, so nothing in the data can be mistaken for one on the way back. Fixed
        // markers could be: the placeholder was '|', so every pipe a shop had written
        // came out of a dump as a semicolon.
        $quotedSemicolon = $this->placeholderAbsentFrom($sql);
        $blockSemicolon = $this->placeholderAbsentFrom($sql);

        $sql = str_replace(";',", $quotedSemicolon, $sql);
        $sql = trim($sql);
        preg_match_all('#DELIMITER (.+?)\n(.+?)DELIMITER ;#s', $sql, $m);

        foreach ($m[0] as $k => $v) {
            // A '|' delimiter used to be rejected here, because '|' was the placeholder
            // above and the two would have collided. Nothing is reserved any more.
            $stored = str_replace([';', $m[1][$k]], [$blockSemicolon, ";\n"], $m[2][$k]);
            $sql = str_replace($v, $stored, $sql);
        }

        $query = [];

        foreach (explode(";\n", $sql) as $iValue) {
            $query[] = str_replace([$quotedSemicolon, $blockSemicolon], [";',", ';'], $iValue);
        }

        return $query;
    }

    /**
     * A token the file does not already contain, so that putting it back cannot alter
     * a value that happened to look like it.
     */
    private function placeholderAbsentFrom(string $sql): string
    {
        do {
            $placeholder = '{{thelia-sql-'.bin2hex(random_bytes(8)).'}}';
        } while (str_contains($sql, $placeholder));

        return $placeholder;
    }

    /**
     * Backup the db OR just a table.
     *
     * The dump is written as it is read: rows come off the server one at a time and
     * leave for the file in INSERT statements of at most INSERT_BATCH_BYTES, so the
     * memory it takes does not grow with the database. Building the whole dump in
     * memory first, on top of a result set the driver had buffered in full, took
     * several times the size of the data, and a large shop could not be backed up.
     */
    public function backupDb(string $filename, string|array $tables = '*'): void
    {
        $tables = $this->tablesToBackup($tables);

        $file = fopen($filename, 'w');

        if (false === $file) {
            throw new \RuntimeException(\sprintf('Unable to open the backup file %s for writing.', $filename));
        }

        try {
            $this->write($file, "\nSET foreign_key_checks=0;\n\n");

            foreach ($tables as $table) {
                if (!preg_match('/^[\\w_\\-]+$/', (string) $table)) {
                    Tlog::getInstance()->alert(
                        \sprintf(
                            "Attempt to backup the db with this invalid table name: '%s'",
                            $table,
                        ),
                    );

                    continue;
                }

                $this->dumpTable($file, (string) $table);
            }

            $this->write($file, self::DUMP_TERMINATOR);
        } catch (\Throwable $throwable) {
            // A dump that stopped half way is no backup. restoreDb() would refuse it
            // anyway, for it lacks the terminator; removing it says so sooner.
            fclose($file);
            @unlink($filename);

            throw $throwable;
        }

        if (!fclose($file)) {
            throw new \RuntimeException(\sprintf('Unable to finish writing the backup file %s.', $filename));
        }
    }

    /**
     * @return array<string>
     */
    private function tablesToBackup(string|array $tables): array
    {
        if ('*' !== $tables) {
            return \is_array($tables) ? $tables : explode(',', $tables);
        }

        $result = $this->connection->prepare('SHOW TABLES');
        $result->execute();

        return $result->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param resource $file
     */
    private function dumpTable($file, string $table): void
    {
        // Asked before the rows are: an unbuffered read keeps the connection busy
        // until its last row has been fetched, and no other query can run meanwhile.
        $structure = $this->execute('SHOW CREATE TABLE `'.$table.'`');
        $createTable = $structure->fetch(\PDO::FETCH_NUM)[1];
        $structure->closeCursor();

        $this->write($file, 'DROP TABLE `'.$table.'`;'."\n\n".$createTable.";\n\n");

        $this->withUnbufferedReads(function () use ($file, $table): void {
            $rows = $this->execute('SELECT * FROM `'.$table.'`');
            $insert = 'INSERT INTO `'.$table.'` VALUES';
            $batch = '';

            try {
                while (false !== ($row = $rows->fetch(\PDO::FETCH_NUM))) {
                    $values = '('.implode(',', array_map($this->quoteForDump(...), $row)).')';

                    if ('' !== $batch && \strlen($batch) + \strlen($values) > self::INSERT_BATCH_BYTES) {
                        $this->write($file, $insert.$batch.";\n");
                        $batch = '';
                    }

                    $batch .= ('' === $batch ? '' : ',').$values;
                }
            } finally {
                $rows->closeCursor();
            }

            if ('' !== $batch) {
                $this->write($file, $insert.$batch.";\n");
            }
        });

        $this->write($file, "\n\n\n");
    }

    /**
     * MySQL hands a whole result set over to the client before the first row is read
     * unless it is told otherwise, and a table of several gigabytes then sits in the
     * memory of the process. The setting is put back as it was: the caller goes on
     * using the connection, and it may rely on buffered reads.
     */
    private function withUnbufferedReads(callable $read): void
    {
        if ('mysql' !== $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            $read();

            return;
        }

        $attribute = self::bufferedQueryAttribute();
        $previous = $this->connection->getAttribute($attribute);
        $this->connection->setAttribute($attribute, false);

        try {
            $read();
        } finally {
            $this->connection->setAttribute($attribute, $previous);
        }
    }

    /**
     * PHP 8.4 moved the driver specific constants to Pdo\Mysql, and 8.5 deprecates
     * the old names on PDO.
     */
    private static function bufferedQueryAttribute(): int
    {
        return \defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            ? \constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
    }

    /**
     * @param resource $file
     */
    private function write($file, string $content): void
    {
        if (fwrite($file, $content) !== \strlen($content)) {
            throw new \RuntimeException('Unable to write the backup file: is the disk full?');
        }
    }

    /**
     * A value as it goes back in through a plain SQL file.
     *
     * NULL stays NULL. Writing it as an empty string is what made a restore die on the
     * first nullable non-string column it met, after having already dropped and rebuilt
     * every table before that one.
     *
     * Everything else is quoted by the connection, which knows the charset it talks in
     * and the escaping mode of the server; addslashes() knew neither.
     *
     * That also settles the newlines, which used to be substituted by hand afterwards:
     * the connection escapes CR, LF and CTRL-Z itself, so no raw newline can reach the
     * file and split a statement in two when prepareSql() cuts the dump on ";\n".
     */
    private function quoteForDump(int|float|string|bool|null $value): string
    {
        if (null === $value) {
            return 'NULL';
        }

        if (\is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return $this->connection->quote((string) $value);
    }

    /**
     * Restore a file in the current db.
     *
     * @param string $filename the file containing sql queries
     */
    public function restoreDb(string $filename): void
    {
        $statements = $this->prepareSql($this->readCompleteDump($filename));

        try {
            $this->replay($statements);
        } finally {
            // A dump turns foreign key checks off so it can be replayed in any order,
            // and turns them back on at its end. A restore that stops before that must
            // not leave them off on a connection the caller goes on using.
            $this->connection->exec('SET foreign_key_checks=1');
        }
    }

    /**
     * @param list<string> $statements
     */
    private function replay(array $statements): void
    {
        $table = null;

        foreach ($statements as $statement) {
            if ('' === trim($statement)) {
                continue;
            }

            if (1 === preg_match('/^\s*DROP TABLE `([^`]+)`/i', $statement, $matches)) {
                $table = $matches[1];
            }

            try {
                $this->execute($statement);
            } catch (\Throwable $throwable) {
                // Say where it stopped. A dump is a DROP, a CREATE and the rows of one
                // table after another, and DDL cannot be rolled back, so what is left
                // is the backup up to this table and the previous content after it.
                throw new \RuntimeException(\sprintf('The restore stopped while rebuilding `%s`. The tables restored before it now hold the backup, the ones after it still hold what they held: %s', $table ?? 'the first statement', $throwable->getMessage()), 0, $throwable);
            }
        }
    }

    /**
     * Reads a dump only if it is whole.
     *
     * The check happens before a single table is dropped: a file cut short by a full
     * disk or a killed process would otherwise be replayed until it runs out, and leave
     * the database part backup, part what it was, with nothing to say so.
     */
    private function readCompleteDump(string $filename): string
    {
        $dump = file_get_contents($filename);

        if (false === $dump || '' === trim($dump)) {
            throw new \RuntimeException(\sprintf('The backup file %s is empty. Nothing was restored.', $filename));
        }

        if (!str_ends_with(rtrim($dump), self::DUMP_TERMINATOR)) {
            throw new \RuntimeException(\sprintf('The backup file %s is truncated: it does not end the way a complete dump ends. Nothing was restored.', $filename));
        }

        return $dump;
    }

    /**
     * create database if not exists.
     */
    public function createDatabase($dbName): void
    {
        $this->execute(
            \sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
                $dbName,
            ),
        );
    }

    /**
     * The handle the update scripts of setup/update/php are given. Propel hands out a
     * PdoConnection, which speaks the same query(), prepare() and exec() as \PDO but
     * does not extend it and keeps its own \PDO private, so a \PDO return type turns
     * every update script into a TypeError.
     */
    public function getConnection(): ConnectionInterface|\PDO
    {
        return $this->connection;
    }
}
