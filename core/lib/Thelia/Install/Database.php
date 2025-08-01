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

namespace Thelia\Install;

use PDO;
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
        $sql = str_replace(";',", '-CODE-', $sql);
        $sql = trim($sql);
        preg_match_all('#DELIMITER (.+?)\n(.+?)DELIMITER ;#s', $sql, $m);

        foreach ($m[0] as $k => $v) {
            if ('|' === $m[1][$k]) {
                throw new \RuntimeException('You can not use "|" as delimiter: '.$v);
            }

            $stored = str_replace([';', $m[1][$k]], ['|', ";\n"], $m[2][$k]);
            $sql = str_replace($v, $stored, $sql);
        }

        $query = [];

        $tab = explode(";\n", $sql);

        foreach ($tab as $iValue) {
            $queryTemp = str_replace(['-CODE-', '|'], [";',", ';'], $iValue);
            $query[] = $queryTemp;
        }

        return $query;
    }

    /**
     * Backup the db OR just a table.
     */
    public function backupDb(string $filename, string|array $tables = '*'): void
    {
        $data = [];

        // get all of the tables
        if ('*' === $tables) {
            $tables = [];
            $result = $this->connection->prepare('SHOW TABLES');
            $result->execute();

            while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } else {
            $tables = \is_array($tables) ? $tables : explode(',', $tables);
        }

        $data[] = "\n";
        $data[] = 'SET foreign_key_checks=0;';
        $data[] = "\n\n";

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

            $result = $this->execute('SELECT * FROM `'.$table.'`');

            $fieldCount = $result->columnCount();

            $data[] = 'DROP TABLE `'.$table.'`;';

            $resultStruct = $this->execute('SHOW CREATE TABLE `'.$table.'`');

            $rowStruct = $resultStruct->fetch(\PDO::FETCH_NUM);

            $data[] = "\n\n";
            $data[] = $rowStruct[1];
            $data[] = ";\n\n";

            for ($i = 0; $i < $fieldCount; ++$i) {
                while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                    $data[] = 'INSERT INTO `'.$table.'` VALUES(';

                    for ($j = 0; $j < $fieldCount; ++$j) {
                        $row[$j] = addslashes((string) $row[$j]);
                        $row[$j] = str_replace("\n", '\\n', $row[$j]);
                        $data[] = '"'.$row[$j].'"';

                        if ($j < ($fieldCount - 1)) {
                            $data[] = ',';
                        }
                    }

                    $data[] = ");\n";
                }
            }

            $data[] = "\n\n\n";
        }

        $data[] = 'SET foreign_key_checks=1;';

        // save filename
        $this->writeFilename($filename, $data);
    }

    /**
     * Restore a file in the current db.
     *
     * @param string $filename the file containing sql queries
     */
    public function restoreDb(string $filename): void
    {
        $this->insertSql(null, [$filename]);
    }

    /**
     * Save an array of data to a filename.
     */
    private function writeFilename(string $filename, array $data): void
    {
        $f = fopen($filename, 'wb+');

        fwrite($f, implode('', $data));
        fclose($f);
    }

    /**
     * create database if not exists.
     */
    public function createDatabase($dbName): void
    {
        $this->execute(
            \sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8',
                $dbName,
            ),
        );
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}
