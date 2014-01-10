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

/**
 * Class Database
 * @package Thelia\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Database
{
    public $connection;

    public function __construct(\PDO $connection)
    {
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

        for ($i = 0; $i < count($sql); $i ++) {
            if (!empty($sql[$i])) {
                $this->connection->query($sql[$i]);
            }
        }
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

        for ($i=0; $i<count($tab); $i++) {
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
        $this->connection->exec(
            sprintf(
                "CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8",
                $dbName
            )
        );
    }
}
