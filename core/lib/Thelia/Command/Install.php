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

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use Thelia\Install\CheckPermission;
use Thelia\Install\Database;
use Thelia\Tools\TokenProvider;

/**
 * try to install a new instance of Thelia
 *
 * Class Install
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Install extends ContainerAwareCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName("thelia:install")
            ->setDescription("Install thelia using cli tools. For now Thelia only use mysql database")
            ->setHelp("The <info>thelia:install</info> command install Thelia database and create config file needed.")
            ->addOption(
                "db_host",
                null,
                InputOption::VALUE_OPTIONAL,
                "host for your database"
            )
            ->addOption(
                "db_username",
                null,
                InputOption::VALUE_OPTIONAL,
                "username for your database"
            )
            ->addOption(
                "db_password",
                null,
                InputOption::VALUE_OPTIONAL,
                "password for your database"
            )
            ->addOption(
                "db_name",
                null,
                InputOption::VALUE_OPTIONAL,
                "database name"
            )
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(array(
            '',
            'Welcome to Thelia install process',
            'You need information about your database configuration (host, username, password, database name, etc)',
            '',
            '<info>Caution : You are installing Thelia in cli mode, we verify some information, but this information are only available for the cli php sapi</info>',
            '<info>This informations can be different in your apache or cgi php.ini files</info>',
            ''
        ));

        $this->checkPermission($output);

        $connectionInfo = array(
            "host" => $input->getOption("db_host"),
            "dbName" => $input->getOption("db_name"),
            "username" => $input->getOption("db_username"),
            "password" => $input->getOption("db_password")
        );

        while (false === $connection = $this->tryConnection($connectionInfo, $output)) {
                $connectionInfo = $this->getConnectionInfo($input, $output);
        }

        $database = new Database($connection);

        $database->createDatabase($connectionInfo["dbName"]);

        $output->writeln(array(
            "",
            "<info>Creating Thelia database, please wait</info>",
            ""
        ));
        $database->insertSql($connectionInfo["dbName"]);
        $this->manageSecret($database);

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

    protected function manageSecret(Database $database)
    {
        $secret = TokenProvider::generateToken();

        $sql = "UPDATE `config` SET `value`=? WHERE `name`='form.secret'";

        $database->execute($sql, [$secret]);
    }

    /**
     * Test if needed directories have write permission
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkPermission(OutputInterface $output)
    {
        $output->writeln(array(
            "Checking some permissions"
        ));

        $permissions = new CheckPermission(false, $this->getContainer()->get('thelia.translator'));
        $isValid = $permissions->exec();

        foreach ($permissions->getValidationMessages() as $item => $data) {
            if ($data['status']) {
                $output->writeln(array(
                    sprintf("<info>%s ...</info> %s",
                        $data['text'],
                        "<info>Ok</info>")
                    )
                );
            } else {
                $output->writeln(array(
                    sprintf("<error>%s </error>%s",
                        $data['text'],
                        sprintf("<error>%s</error>", $data["hint"])
                    )
                ));
            }

        }

        if (false === $isValid) {
            throw new \RuntimeException('Please put correct permissions and reload install process');
        }
    }

    /**
     * rename database config file and complete it
     *
     * @param array $connectionInfo
     */
    protected function createConfigFile($connectionInfo)
    {
        $fs = new Filesystem();

        $sampleConfigFile = THELIA_CONF_DIR . "database.yml.sample";
        $configFile = THELIA_CONF_DIR . "database.yml";

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

        $fs->remove($this->getContainer()->getParameter("kernel.cache_dir"));

    }

    /**
     * test database access
     *
     * @param $connectionInfo
     * @param  OutputInterface $output
     * @return bool|\PDO
     */
    protected function tryConnection($connectionInfo, OutputInterface $output)
    {

        if (is_null($connectionInfo["dbName"])) {
            return false;
        }

        $dsn = "mysql:host=%s";

        try {
            $connection = new \PDO(
                sprintf($dsn, $connectionInfo["host"]),
                $connectionInfo["username"],
                $connectionInfo["password"]
            );
            $connection->query('SET NAMES \'UTF8\'');
        } catch (\PDOException $e) {
            $output->writeln(array(
                "<error>Wrong connection information</error>"
            ));

            return false;
        }

        return $connection;
    }

    /**
     * Ask to user all needed information
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return array
     */
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
                    throw new \RuntimeException("You must specify a database host");
                }

                return $answer;
            }
        );

        $connectionInfo["dbName"] = $dialog->askAndValidate(
            $output,
            $this->decorateInfo("Database name (if database does not exist, Thelia will try to create it) : "),
            function ($answer) {
                $answer = trim($answer);

                if (is_null($answer)) {
                    throw new \RuntimeException("You must specify a database name");
                }

                return $answer;
            }
        );

        $connectionInfo["username"] = $dialog->askAndValidate(
            $output,
            $this->decorateInfo("Database username : "),
            function ($answer) {
                $answer = trim($answer);

                if (is_null($answer)) {
                    throw new \RuntimeException("You must specify a database username");
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
