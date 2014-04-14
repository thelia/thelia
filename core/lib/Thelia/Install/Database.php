<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;

/**
 * Class Database
 * @package Thelia\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
     * @param ConnectionInterface|\PDO|null $connection the connection object
     * @throws \InvalidArgumentException if $connection is not of the suitable type.
     */
    public function __construct($connection = null)
    {
        // Get a connection from Propel if we don't have one
        if (null == $connection) {
            $connection = Propel::getConnection(ServiceContainerInterface::CONNECTION_WRITE);
        }

        // Get the PDO connection from an
        if ($connection instanceof ConnectionWrapper)
            $connection = $connection->getWrappedConnection();

        if (! $connection instanceof \PDO) {
            throw new \InvalidArgumentException("A PDO connection shoud be provided");
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
            $this->connection->query(sprintf("use %s", $dbName));
        }

        $sql = array();

        if (null === $extraSqlFiles) {
            $sql = array_merge(
                $sql,
                $this->prepareSql(file_get_contents(THELIA_ROOT . '/install/thelia.sql')),
                $this->prepareSql(file_get_contents(THELIA_ROOT . '/install/insert.sql'))
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
        for ($i = 0; $i < $size; $i ++) {
            if (!empty($sql[$i])) {
                $this->execute($sql[$i]);
            }
        }
    }

    /**
     * A simple wrapper around PDO::exec
     *
     * @param string $sql SQL query
     * @param array $args SQL request parameters (PDO style)
     */
    public function execute($sql, $args = array()) {
        $this->connection->query($sql, $args);
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
        for ($i=0; $i<$size; $i++) {
            $queryTemp = str_replace("-CODE-", ";',", $tab[$i]);
            $queryTemp = str_replace("|", ";", $queryTemp);
            $query[] = $queryTemp;
        }

        return $query;
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
                "CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8",
                $dbName
            )
        );
    }
}
