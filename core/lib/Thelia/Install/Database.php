<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Install;

use PDO;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Thelia\Log\Tlog;

/**
 * Class Database
 * @package Thelia\Install
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Database
{
    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * Create a new instance, using the provided connection information, either none for
     * automatically a connection, a ConnectionWrapper instance (through ConnectionInterface) or a PDO connection.
     *
     * @param  ConnectionInterface|\PDO|null $connection the connection object
     * @throws \InvalidArgumentException     if $connection is not of the suitable type.
     */
    public function __construct($connection = null)
    {
        // Get a connection from Propel if we don't have one
        if (null == $connection) {
            $connection = Propel::getConnection(ServiceContainerInterface::CONNECTION_WRITE);
        }

        // Get the PDO connection from an
        if ($connection instanceof ConnectionWrapper) {
            $connection = $connection->getWrappedConnection();
        }

        if (!$connection instanceof \PDO) {
            throw new \InvalidArgumentException("A PDO connection should be provided");
        }

        $this->connection = $connection;
    }

    /**
     * Insert all sql needed in database
     * Default insert /install/thelia.sql and /install/insert.sql
     *
     * @param string $dbName        Database name
     * @param array  $extraSqlFiles SQL Files uri to insert
     */
    public function insertSql($dbName = null, array $extraSqlFiles = null)
    {
        if ($dbName) {
            $this->connection->query(sprintf("use `%s`", $dbName));
        }

        $sql = array();

        if (null === $extraSqlFiles) {
            $sql = array_merge(
                $sql,
                $this->prepareSql(file_get_contents(THELIA_SETUP_DIRECTORY . 'thelia.sql')),
                $this->prepareSql(file_get_contents(THELIA_SETUP_DIRECTORY . 'insert.sql'))
            );
        } else {
            foreach ($extraSqlFiles as $fileToInsert) {
                $sql = array_merge(
                    $sql,
                    $this->prepareSql(file_get_contents($fileToInsert))
                );
            }
        }
        $size = count($sql);
        for ($i = 0; $i < $size; $i++) {
            if (!empty($sql[$i])) {
                $this->execute($sql[$i]);
            }
        }
    }

    /**
     * A simple wrapper around PDO::exec
     *
     * @param  string                          $sql  SQL query
     * @param  array                           $args SQL request parameters (PDO style)
     * @throws \RuntimeException|\PDOException if something goes wrong.
     * @return \PDOStatement
     */
    public function execute($sql, $args = array())
    {
        $stmt = $this->connection->prepare($sql);

        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement for $sql: " . print_r($this->connection->errorInfo(), 1));
        }

        $success = $stmt->execute($args);

        if ($success === false || $stmt->errorCode() != 0) {
            throw new \RuntimeException("Failed to execute SQL '$sql', arguments:" . print_r($args, 1).", error:".print_r($stmt->errorInfo(), 1));
        }

        return $stmt;
    }

    /**
     * Separate each sql instruction in an array
     *
     * @param $sql
     * @return array
     */
    protected function prepareSql($sql)
    {
        $sql = str_replace(";',", "-CODE-", $sql);
        $sql = trim($sql);
        $query = array();

        $tab = explode(";\n", $sql);
        $size = count($tab);
        for ($i = 0; $i < $size; $i++) {
            $queryTemp = str_replace("-CODE-", ";',", $tab[$i]);
            $queryTemp = str_replace("|", ";", $queryTemp);
            $query[] = $queryTemp;
        }

        return $query;
    }

    /**
     * Backup the db OR just a table
     *
     * @param string $filename
     * @param string $tables
     */
    public function backupDb($filename, $tables = '*')
    {
        $data = [];

        // get all of the tables
        if ($tables == '*') {
            $tables = array();
            $result = $this->connection->prepare('SHOW TABLES');
            $result->execute();
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        $data[] = "\n";
        $data[] = 'SET foreign_key_checks=0;';
        $data[] = "\n\n";

        foreach ($tables as $table) {
            if (!preg_match("/^[\w_\-]+$/", $table)) {
                Tlog::getInstance()->alert(
                    sprintf(
                        "Attempt to backup the db with this invalid table name: '%s'",
                        $table
                    )
                );

                continue;
            }

            $result = $this->execute('SELECT * FROM `' . $table . '`');

            $fieldCount = $result->columnCount();

            $data[] = 'DROP TABLE `' . $table . '`;';

            $resultStruct = $this->execute('SHOW CREATE TABLE `' . $table . '`');

            $rowStruct = $resultStruct->fetch(PDO::FETCH_NUM);

            $data[] = "\n\n";
            $data[] = $rowStruct[1];
            $data[] = ";\n\n";

            for ($i = 0; $i < $fieldCount; $i++) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $data[] = 'INSERT INTO `' . $table . '` VALUES(';
                    for ($j = 0; $j < $fieldCount; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $data[] = '"' . $row[$j] . '"';
                        } else {
                            $data[] = '""';
                        }
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

        //save filename
        $this->writeFilename($filename, $data);
    }


    /**
     * Restore a file in the current db
     *
     * @param string $filename the file containing sql queries
     */
    public function restoreDb($filename)
    {
        $this->insertSql(null, [$filename]);
    }

    /**
     * Save an array of data to a filename
     *
     * @param string $filename
     * @param array $data
     */
    private function writeFilename($filename, $data)
    {
        $f = fopen($filename, "w+");

        fwrite($f, implode('', $data));
        fclose($f);
    }

    /**
     * create database if not exists
     *
     * @param $dbName
     */
    public function createDatabase($dbName)
    {
        $this->execute(
            sprintf(
                "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8",
                $dbName
            )
        );
    }

    /**
     * @return PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
