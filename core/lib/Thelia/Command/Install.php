<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Tests\Compiler\CheckExceptionOnInvalidReferenceBehaviorPassTest;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Core\Event\TheliaEvents;


class Install extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("thelia:install")
            ->setDescription("Install thelia using cli tools. For now Thelia only use mysql database")
            ->setHelp("The <info>thelia:install</info> command install Thelia database and create config file needed.")
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(array(
            '',
            'Welcome to Thelia install process',
            'You need information about your database configuration (host, username, password, database name, etc)',
            ''
        ));

        $this->checkPermission($output);

        do {
            $connectionInfo = $this->getConnectionInfo($input, $output);
        } while(false === $connection = $this->tryConnection($connectionInfo, $output));

        $this->createDatabase($connection, $connectionInfo["dbName"]);

        $output->writeln(array(
            "",
            "<info>Creating Thelia database, please wait</info>",
            ""
        ));
        $this->insertSql($connection, $connectionInfo["dbName"]);

        $output->writeln(array(
            "",
            "<info>Database created without errors</info>",
            "<info>Creating file configuration, please wait</info>",
            ""
        ));

        $this->createConfigFile($connectionInfo);

        $output->writeln(array(
            "",
            "<info>Config file created with success. Your thelia is installed</info>",
            ""
        ));
    }

    protected function checkPermission(OutputInterface $output)
    {
        $output->writeln(array(
            "Checking some permissions"
        ));

        $confDir = THELIA_ROOT . "local/config";
        $cacheDir = THELIA_ROOT . "cache";
        $logDir = THELIA_ROOT . "log";

        $conf   = is_writable($confDir);
        $cache  = is_writable($cacheDir);
        $log    = is_writable($logDir);

        $output->writeln(array(
           sprintf(
               "<info>config directory(%s)...</info> %s",
               $confDir,
               $conf ? "<info>Ok</info>" : "<error>Fail</error>"
           ),
           sprintf(
               "<info>cache directory(%s)...</info> %s"
               ,$cacheDir,
               $cache ? "<info>Ok</info>" : "<error>Fail</error>"
           ),
           sprintf(
               "<info>log directory(%s)...</info> %s",
               $logDir,
               $log ? "<info>Ok</info>" : "<error>Fail</error>"
           ),
        ));

        if ($conf === false || $cache === false || $log === false) {
           $output->writeln(array(
              "",
              "<error>Please put correct permission and reload install process</error>"
           ));
            exit;
        }



    }

    protected function createConfigFile($connectionInfo)
    {
        $fs = new Filesystem();

        $sampleConfigFile = THELIA_ROOT . "/local/config/database.yml.sample";
        $configFile = THELIA_ROOT . "/local/config/database.yml";


        $fs->copy($sampleConfigFile, $configFile, true);

        $configContent = file_get_contents($configFile);

        $configContent = str_replace("%DRIVER%", "mysql", $configContent);
        $configContent = str_replace("%USERNAME%", $connectionInfo["username"], $configContent);
        $configContent = str_replace("%PASSWORD%", $connectionInfo["password"], $configContent);
        $configContent = str_replace(
            "%DSN%",
            sprintf("mysql:host=%s;dbname=%s", $connectionInfo["host"], $connectionInfo["dbName"]),
            $configContent
        );

        file_put_contents($configFile, $configContent);

        $fs->remove($sampleConfigFile);

        $fs->remove($this->getContainer()->getParameter("kernel.cache_dir"));


    }

    protected function insertSql(\PDO $connection, $dbName)
    {
        $connection->query(sprintf("use %s", $dbName));
        $sql = array();
        $sql = array_merge(
            $sql,
            $this->prepareSql(file_get_contents(THELIA_ROOT . "/install/thelia.sql")),
            $this->prepareSql(file_get_contents(THELIA_ROOT . "/install/insert.sql"))
        );

        for ($i = 0; $i < count($sql); $i ++) {
            $connection->query($sql[$i]);
        }
    }

    protected function prepareSql($sql)
    {
        $sql = str_replace(";',", "-CODE-", $sql);
        $query = array();

        $tab = explode(";", $sql);

        for($i=0; $i<count($tab); $i++){
            $queryTemp = str_replace("-CODE-", ";',", $tab[$i]);
            $queryTemp = str_replace("|", ";", $queryTemp);
            $query[] = $queryTemp;
        }

        return $query;
    }

    protected function createDatabase(\PDO $connection, $dbName)
    {
        $connection->query(
            sprintf(
                "CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8",
                $dbName
            )
        );
    }

    protected function tryConnection($connectionInfo, OutputInterface $output)
    {

        $dsn = "mysql:host=%s";

        try {
            $connection = new \PDO(
                sprintf($dsn, $connectionInfo["host"]),
                $connectionInfo["username"],
                $connectionInfo["password"]
            );
        } catch (\PDOException $e) {
            $output->writeln(array(
                "<error>Wrong connection information</error>"
            ));
            return false;
        }



        return $connection;
    }

    protected function getConnectionInfo(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');

        $connectionInfo = array();

        $connectionInfo["host"] = $dialog->askAndValidate(
            $output,
            $this->decorateInfo("Database host : "),
            function ($answer) {
                $answer = trim($answer);
                if (is_null($answer)) {
                    throw new \RuntimeException("You must specify database host");
                }

                return $answer;
            }
        );

        $connectionInfo["dbName"] = $dialog->askAndValidate(
            $output,
            $this->decorateInfo("Database Name (if database does not exists, Thelia will try to create it) : "),
            function ($answer) {
                $answer = trim($answer);

                if (is_null($answer)) {
                    throw new \RuntimeException("You must specify database name");
                }

                return $answer;
            }
        );

        $connectionInfo["username"] = $dialog->askAndValidate(
            $output,
            $this->decorateInfo("Databse username : "),
            function ($answer) {
                $answer = trim($answer);

                if (is_null($answer)) {
                    throw new \RuntimeException("You must sprcify database username");
                }

                return $answer;
            }
        );

        $connectionInfo["password"] = $dialog->askHiddenResponse(
            $output,
            $this->decorateInfo("Database password : ")
        );

        return $connectionInfo;
    }

    protected function decorateInfo($text)
    {
        return sprintf("<info>%s</info>", $text);
    }

}