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

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Install\CheckPermission;
use Thelia\Core\Install\Database;
use Thelia\Domain\Module\Composer\ComposerHelper;
use Thelia\Tools\TokenProvider;

/**
 * try to install a new instance of Thelia.
 *
 * Class Install
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'thelia:install', description: 'Install thelia using cli tools. For now Thelia only use mysql database')]
class Install extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setHelp('The <info>thelia:install</info> command install Thelia database and create config file needed.')
            ->addOption(
                'database_host',
                null,
                InputOption::VALUE_OPTIONAL,
                'host for your database',
                'localhost',
            )
            ->addOption(
                'database_username',
                null,
                InputOption::VALUE_OPTIONAL,
                'username for your database',
            )
            ->addOption(
                'database_password',
                null,
                InputOption::VALUE_OPTIONAL,
                'password for your database',
            )
            ->addOption(
                'database_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'database name',
            )
            ->addOption(
                'database_port',
                null,
                InputOption::VALUE_OPTIONAL,
                'database port',
                '3306',
            )
            ->addOption(
                'frontoffice_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'frontoffice theme',
                'flexy',
            )
            ->addOption(
                'backoffice_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'backoffice theme',
                'default',
            )
            ->addOption(
                'pdf_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'pdf theme',
                'default',
            )
            ->addOption(
                'email_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'email theme',
                'default',
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

        $this->handleThemesBundle($input, $output);

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

        foreach ($permissions->getValidationMessages() as $data) {
            if ($data['status']) {
                $output->writeln(
                    [
                        \sprintf(
                            '<info>%s ...</info> %s',
                            $data['text'],
                            '<info>Ok</info>',
                        ),
                    ],
                );
            } else {
                $output->writeln([
                    \sprintf(
                        '<error>%s </error>%s',
                        $data['text'],
                        \sprintf('<error>%s</error>', $data['hint']),
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
     */
    protected function createConfigFile(array $connectionInfo): void
    {
        $fs = new Filesystem();

        if (!$fs->exists(THELIA_ROOT.'.env.local')) {
            $fs->touch(THELIA_ROOT.'.env.local');
        }

        file_put_contents(
            THELIA_ROOT.'.env.local',
            \sprintf(
                "\n###> thelia/database-configuration ###\nDATABASE_HOST=%s\nDATABASE_PORT=%s\nDATABASE_NAME=%s\nDATABASE_USER=%s\nDATABASE_PASSWORD=%s\n###< thelia/database-configuration ###",
                $connectionInfo['host'],
                $connectionInfo['port'],
                $connectionInfo['dbName'],
                $connectionInfo['username'],
                $connectionInfo['password'],
            ),
            \FILE_APPEND,
        );
    }

    /**
     * test database access.
     */
    protected function tryConnection(array $connectionInfo, OutputInterface $output): false|\PDO
    {
        if (null === $connectionInfo['dbName']) {
            return false;
        }

        $dsn = 'mysql:host=%s;port=%s';

        try {
            $connection = new \PDO(
                \sprintf($dsn, $connectionInfo['host'], $connectionInfo['port']),
                $connectionInfo['username'],
                $connectionInfo['password'],
            );
            $connection->query("SET NAMES 'UTF8'");
        } catch (\PDOException) {
            $output->writeln([
                '<error>Wrong connection information</error>',
            ]);

            return false;
        }

        return $connection;
    }

    /**
     * Ask to user all needed information.
     */
    protected function getConnectionInfo(InputInterface $input, OutputInterface $output): array
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
            'localhost',
        );

        $connectionInfo['port'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database port [default: 3306] : ',
            'You must specify a database port',
            false,
            '3306',
        );

        $connectionInfo['dbName'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database name (if database does not exist, Thelia will try to create it) : ',
            'You must specify a database name',
        );

        $connectionInfo['username'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database username : ',
            'You must specify a database username',
        );

        $connectionInfo['password'] = $this->enterData(
            $helper,
            $input,
            $output,
            'Database password : ',
            'You must specify a database username',
            true,
            null,
            true,
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
    ): mixed {
        $question = new Question($label, $defaultValue);

        if ($hidden) {
            $question->setHidden(true);
            $question->setHiddenFallback(false);
        }

        $question->setValidator(static function ($value) use (&$errorMessage, &$beEmpty) {
            if ('' === trim($value) && (null === $value && !$beEmpty)) {
                throw new \Exception($errorMessage);
            }

            return $value;
        });

        return $helper->ask($input, $output, $question);
    }

    private function handleThemesBundle(InputInterface $input, OutputInterface $output): void
    {
        $themes = [
            'frontOffice' => (string) $input->getOption('frontoffice_theme'),
            'backOffice' => (string) $input->getOption('backoffice_theme'),
            'pdf' => (string) $input->getOption('pdf_theme'),
            'email' => (string) $input->getOption('email_theme'),
        ];

        foreach ($themes as $type => $name) {
            if ('' !== $name) {
                $this->maybeRemoveBundleForTheme($type, $name);
            }
        }
    }

    private function maybeRemoveBundleForTheme(string $type, string $themeName): void
    {
        $pathsToCheck = [];

        $vendorPath = THELIA_VENDOR_ROOT.$themeName;
        if (is_dir($vendorPath)) {
            $pathsToCheck[] = $vendorPath;
        }

        $templatePath = THELIA_TEMPLATE_DIR.$type.DS.$themeName;
        if (is_dir($templatePath)) {
            $pathsToCheck[] = $templatePath;
        }

        if ([] === $pathsToCheck) {
            return;
        }

        $helper = new ComposerHelper();
        $seen = [];

        foreach ($pathsToCheck as $path) {
            $bundleFqcn = $helper->findFirstClassBundle($path);

            if (null === $bundleFqcn) {
                continue;
            }

            if (isset($seen[$bundleFqcn])) {
                continue;
            }

            $this->removeBundleFromSymfonyBundles($bundleFqcn);
            $seen[$bundleFqcn] = true;
        }
    }

    private function removeBundleFromSymfonyBundles(string $bundleFqcn): void
    {
        $bundlesPath = THELIA_ROOT.'config'.DS.'bundles.php';

        if (!file_exists($bundlesPath)) {
            return;
        }

        $bundles = require $bundlesPath;

        if (isset($bundles[$bundleFqcn])) {
            unset($bundles[$bundleFqcn]);
            $this->dumpBundlesPhp($bundles, $bundlesPath);
        }
    }

    private function dumpBundlesPhp(array $bundles, string $bundlesPath): void
    {
        ksort($bundles);

        $lines = ["<?php\n", "return [\n"];

        foreach ($bundles as $fqcn => $envs) {
            $envParts = [];

            foreach ($envs as $env => $enabled) {
                $envParts[] = \sprintf("'%s' => ", $env).($enabled ? 'true' : 'false');
            }

            $lines[] = \sprintf('    %s::class => [', $fqcn).implode(', ', $envParts)."],\n";
        }

        $lines[] = "];\n";

        file_put_contents($bundlesPath, implode('', $lines));
    }
}
