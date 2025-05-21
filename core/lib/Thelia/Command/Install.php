<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Install\CheckPermission;
use Thelia\Install\Database;
use Thelia\Tools\TokenProvider;

/**
 * try to install a new instance of Thelia.
 *
 * Class Install
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Install extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setName('thelia:install')
            ->setDescription('Install thelia using cli tools. For now Thelia only use mysql database')
            ->setHelp('The <info>thelia:install</info> command install Thelia database and create config file needed.')
            ->addOption(
                'database_host',
                null,
                InputOption::VALUE_OPTIONAL,
                'host for your database',
                'localhost'
            )
            ->addOption(
                'database_username',
                null,
                InputOption::VALUE_OPTIONAL,
                'username for your database'
            )
            ->addOption(
                'database_password',
                null,
                InputOption::VALUE_OPTIONAL,
                'password for your database'
            )
            ->addOption(
                'database_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'database name'
            )
            ->addOption(
                'database_port',
                null,
                InputOption::VALUE_OPTIONAL,
                'database port',
                '3306'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            '',
            'Welcome to Thelia install process',
            'You need information about your database configuration (host, username, password, database name, etc)',
            '',
            '<info>Caution : You are installing Thelia in cli mode, we verify some information, but this information are only available for the cli php sapi</info>',
            '<info>This informations can be different in your apache or cgi php.ini files</info>',
            '',
        ]);

        $this->checkPermission($output);

        $connectionInfo = [
            'host' => $input->getOption('database_host'),
            'dbName' => $input->getOption('database_name'),
            'username' => $input->getOption('database_username'),
            'password' => $input->getOption('database_password'),
            'port' => $input->getOption('database_port'),
        ];

        while (false === $connection = $this->tryConnection($connectionInfo, $output)) {
            $connectionInfo = $this->getConnectionInfo($input, $output);
        }

        $database = new Database($connection);

        $database->createDatabase($connectionInfo['dbName']);

        $output->writeln([
            '',
            '<info>Creating Thelia database, please wait</info>',
            '',
        ]);
        $database->insertSql($connectionInfo['dbName']);
        $this->manageSecret($database);

        $output->writeln([
            '',
            '<info>Database created without errors</info>',
            '<info>Creating file configuration, please wait</info>',
            '',
        ]);

        $this->createConfigFile($connectionInfo);

        $output->writeln([
            '',
            '<info>Config file created with success. Your thelia is installed</info>',
            '',
        ]);

        return Command::SUCCESS;
    }

    protected function manageSecret(Database $database): void
    {
        $secret = TokenProvider::generateToken();
        $sql = "UPDATE `config` SET `value`=? WHERE `name`='form.secret'";
        $database->execute($sql, [$secret]);
    }

    /**
     * Test if needed directories have write permission.
     */
    protected function checkPermission(OutputInterface $output): void
    {
        $output->writeln([
            'Checking some permissions',
        ]);

        $permissions = new CheckPermission();
        $isValid = $permissions->exec();

        foreach ($permissions->getValidationMessages() as $item => $data) {
            if ($data['status']) {
                $output->writeln(
                    [
                        sprintf(
                            '<info>%s ...</info> %s',
                            $data['text'],
                            '<info>Ok</info>'
                        ),
                    ]
                );
            } else {
                $output->writeln([
                    sprintf(
                        '<error>%s </error>%s',
                        $data['text'],
                        sprintf('<error>%s</error>', $data['hint'])
                    ),
                ]);
            }
        }

        if (false === $isValid) {
            throw new \RuntimeException('Please put correct permissions and reload install process');
        }
    }

    /**
     * rename database config file and complete it.
     *
     * @param array $connectionInfo
     */
    protected function createConfigFile($connectionInfo): void
    {
        $fs = new Filesystem();

        if (!$fs->exists(THELIA_ROOT.'.env.local')) {
            $fs->touch(THELIA_ROOT.'.env.local');
        }

        file_put_contents(
            THELIA_ROOT.'.env.local',
            sprintf(
                "\n###> thelia/database-configuration ###\nDATABASE_HOST=%s\nDATABASE_PORT=%s\nDATABASE_NAME=%s\nDATABASE_USER=%s\nDATABASE_PASSWORD=%s\n###< thelia/database-configuration ###",
                $connectionInfo['host'],
                $connectionInfo['port'],
                $connectionInfo['dbName'],
                $connectionInfo['username'],
                $connectionInfo['password']
            ),
            \FILE_APPEND
        );

        $fs->remove($this->getContainer()->getParameter('kernel.cache_dir'));
    }

    /**
     * test database access.
     *
     * @return bool|\PDO
     */
    protected function tryConnection($connectionInfo, OutputInterface $output)
    {
        if (null === $connectionInfo['dbName']) {
            return false;
        }

        $dsn = 'mysql:host=%s;port=%s';

        try {
            $connection = new \PDO(
                sprintf($dsn, $connectionInfo['host'], $connectionInfo['port']),
                $connectionInfo['username'],
                $connectionInfo['password']
            );
            $connection->query('SET NAMES \'UTF8\'');
        } catch (\PDOException $e) {
            $output->writeln([
                '<error>Wrong connection information</error>',
            ]);

            return false;
        }

        return $connection;
    }

    /**
     * Ask to user all needed information.
     *
     * @return array
     */
    protected function getConnectionInfo(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $connectionInfo = [];

        $connectionInfo['host'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database host [default: localhost] : ',
            'You must specify a database host',
            false,
            'localhost'
        );

        $connectionInfo['port'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database port [default: 3306] : ',
            'You must specify a database port',
            false,
            '3306'
        );

        $connectionInfo['dbName'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database name (if database does not exist, Thelia will try to create it) : ',
            'You must specify a database name'
        );

        $connectionInfo['username'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database username : ',
            'You must specify a database username'
        );

        $connectionInfo['password'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database password : ',
            'You must specify a database username',
            true,
            null,
            true
        );

        return $connectionInfo;
    }

    protected function enterData(
        QuestionHelper $helper,
        InputInterface $input,
        OutputInterface $output,
        $label,
        $errorMessage,
        $hidden = false,
        $defaultValue = null,
        $beEmpty = false,
    ) {
        $question = new Question($label, $defaultValue);

        if ($hidden) {
            $question->setHidden(true);
            $question->setHiddenFallback(false);
        }

        $question->setValidator(function ($value) use (&$errorMessage, &$beEmpty) {
            if (trim($value) == '') {
                if (null === $value && !$beEmpty) {
                    throw new \Exception($errorMessage);
                }
            }

            return $value;
        });

        return $helper->ask($input, $output, $question);
    }
}
