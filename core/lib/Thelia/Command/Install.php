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

use App\Kernel as AppKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkConsoleApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Thelia\Core\Install\CheckPermission;
use Thelia\Core\Install\Database;
use Thelia\Domain\Module\Composer\ComposerHelper;
use Thelia\Tools\TokenProvider;

/**
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'thelia:install', description: 'Install thelia using cli tools. For now Thelia only use mysql database')]
class Install extends ContainerAwareCommand
{
    public function __construct(
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>thelia:install</info> command install Thelia database and create config file needed.')
            // Database options
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
            // Theme options
            ->addOption(
                'frontoffice_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'frontoffice theme',
            )
            ->addOption(
                'backoffice_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'backoffice theme',
            )
            ->addOption(
                'pdf_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'pdf theme',
            )
            ->addOption(
                'email_theme',
                null,
                InputOption::VALUE_OPTIONAL,
                'email theme',
            )
            ->addOption(
                'with-demo',
                null,
                InputOption::VALUE_NONE,
                'Import demo data after installation (default: no)',
            )
            // Admin user options
            ->addOption(
                'with-admin',
                null,
                InputOption::VALUE_NONE,
                'Create an admin user after installation (default: no)',
            )
            ->addOption('admin_login_name', null, InputOption::VALUE_OPTIONAL, 'Admin login name')
            ->addOption('admin_first_name', null, InputOption::VALUE_OPTIONAL, 'Admin first name')
            ->addOption('admin_last_name', null, InputOption::VALUE_OPTIONAL, 'Admin last name')
            ->addOption('admin_email', null, InputOption::VALUE_OPTIONAL, 'Admin email address')
            ->addOption('admin_locale', null, InputOption::VALUE_OPTIONAL, 'Admin preferred locale (default: en_US)')
            ->addOption('admin_password', null, InputOption::VALUE_OPTIONAL, 'Admin password')
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
            if (!$input->isInteractive()) {
                throw new \RuntimeException('Database connection failed in non-interactive mode. Please check your database credentials (--database_host, --database_username, --database_password, --database_name, --database_port).');
            }
            $connectionInfo = $this->getConnectionInfo($input, $output);
        }

        $themes = $this->getThemesInfo($input, $output);

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

        $this->applyTemplatesInSameCommandProcess($output, $connectionInfo, $themes);

        $this->maybeImportDemoData($input, $output, $connectionInfo);
        $this->maybeCreateAdminUser($input, $output, $connectionInfo);

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

        $envFile = 'test' === $this->environment ? '.env.test.local' : '.env.local';
        $envFilePath = THELIA_ROOT.$envFile;

        if (!$fs->exists($envFilePath)) {
            $fs->touch($envFilePath);
        }

        file_put_contents(
            $envFilePath,
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

    private function getThemesInfo(InputInterface $input, OutputInterface $output): array
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $themes = [
            'frontOffice' => $input->getOption('frontoffice_theme'),
            'backOffice' => $input->getOption('backoffice_theme'),
            'pdf' => $input->getOption('pdf_theme'),
            'email' => $input->getOption('email_theme'),
        ];

        $defaults = [
            'frontOffice' => 'flexy',
            'backOffice' => 'default',
            'pdf' => 'default',
            'email' => 'default',
        ];

        foreach ($themes as $type => $value) {
            $value = \is_string($value) ? trim($value) : null;

            if (null !== $value && '' !== $value) {
                $themes[$type] = $value;
                continue;
            }

            $label = match ($type) {
                'frontOffice' => 'Front office theme',
                'backOffice' => 'Back office theme',
                'pdf' => 'PDF theme',
                'email' => 'Email theme',
                default => 'Theme',
            };

            $themes[$type] = (string) $this->enterData(
                $helper,
                $input,
                $output,
                $label.\sprintf(' [default: %s] : ', $defaults[$type]),
                'You must specify a theme name',
                false,
                $defaults[$type],
            );
        }

        return $themes;
    }

    private function applyTemplatesInSameCommandProcess(
        OutputInterface $output,
        array $connectionInfo,
        array $themes,
    ): void {
        $this->publishDatabaseEnvironmentForCurrentProcess($connectionInfo);

        if (!class_exists(AppKernel::class)) {
            throw new \RuntimeException('App\\Kernel is missing. Post-install steps require the application kernel.');
        }
        foreach ($themes as $type => $name) {
            $name = trim((string) $name);

            if ('' === $name) {
                continue;
            }

            $output->writeln(\sprintf(
                '<info>Applying template "%s" for type "%s"...</info>',
                $name,
                $type,
            ));

            $kernel = new AppKernel($_SERVER['APP_ENV'], (bool) ($_SERVER['APP_DEBUG'] ?? false));
            $kernel->boot();

            try {
                $application = new FrameworkConsoleApplication($kernel);
                $application->setAutoExit(false);
                $exitCode = $application->run(
                    new ArrayInput([
                        'command' => 'template:set',
                        'type' => $type,
                        'name' => $name,
                    ]),
                    $output,
                );

                if (Command::SUCCESS !== $exitCode) {
                    $output->writeln(
                        \sprintf(
                            '<error>Post-install step failed while applying template "%s" for type "%s".</error>',
                            $name,
                            $type
                        )
                    );
                }
            } finally {
                $kernel->shutdown();
            }
        }
    }

    private function publishDatabaseEnvironmentForCurrentProcess(array $connectionInfo): void
    {
        $values = [
            'DATABASE_HOST' => (string) $connectionInfo['host'],
            'DATABASE_PORT' => (string) $connectionInfo['port'],
            'DATABASE_NAME' => (string) $connectionInfo['dbName'],
            'DATABASE_USER' => (string) $connectionInfo['username'],
            'DATABASE_PASSWORD' => (string) $connectionInfo['password'],
        ];

        foreach ($values as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }
    }

    private function maybeImportDemoData(InputInterface $input, OutputInterface $output, array $connectionInfo): void
    {
        $shouldImport = (bool) $input->getOption('with-demo');

        if (!$shouldImport && $input->isInteractive()) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<info>Import demo data?</info> [no]: ', false);
            $shouldImport = (bool) $helper->ask($input, $output, $question);
        }

        if (!$shouldImport) {
            return;
        }

        $output->writeln('<info>Importing demo data...</info>');

        $process = $this->createTheliaCliProcess(
            $connectionInfo,
            [\PHP_BINARY, THELIA_ROOT.'Thelia', 'thelia:demo:import'],
        );

        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Demo import failed.');
        }
    }

    private function maybeCreateAdminUser(InputInterface $input, OutputInterface $output, array $connectionInfo): void
    {
        $shouldCreate = (bool) $input->getOption('with-admin');

        if (!$shouldCreate && $input->isInteractive()) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<info>Create an admin user?</info> [no]: ', false);
            $shouldCreate = (bool) $helper->ask($input, $output, $question);
        }

        if (!$shouldCreate) {
            return;
        }

        $adminData = $this->resolveAdminData($input, $output);

        $output->writeln('<info>Creating admin user...</info>');

        $process = $this->createTheliaCliProcess(
            $connectionInfo,
            [
                \PHP_BINARY,
                THELIA_ROOT.'Thelia',
                'admin:create',
                '--login_name='.$adminData['login_name'],
                '--first_name='.$adminData['first_name'],
                '--last_name='.$adminData['last_name'],
                '--email='.$adminData['email'],
                '--locale='.$adminData['locale'],
                '--password='.$adminData['password'],
            ],
        );

        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Admin creation failed.');
        }
    }

    private function resolveAdminData(InputInterface $input, OutputInterface $output): array
    {
        $login = $input->getOption('admin_login_name');
        $firstName = $input->getOption('admin_first_name');
        $lastName = $input->getOption('admin_last_name');
        $email = $input->getOption('admin_email');
        $locale = $input->getOption('admin_locale') ?: 'en_US';
        $password = $input->getOption('admin_password');

        if (!$input->isInteractive()) {
            if (!\is_string($login) || '' === trim($login)) {
                throw new \RuntimeException('Missing required option: --admin_login_name (non-interactive mode).');
            }
            if (!\is_string($firstName) || '' === trim($firstName)) {
                throw new \RuntimeException('Missing required option: --admin_first_name (non-interactive mode).');
            }
            if (!\is_string($lastName) || '' === trim($lastName)) {
                throw new \RuntimeException('Missing required option: --admin_last_name (non-interactive mode).');
            }
            if (!\is_string($password) || '' === trim($password)) {
                throw new \RuntimeException('Missing required option: --admin_password (non-interactive mode).');
            }

            return [
                'login_name' => trim($login),
                'first_name' => trim($firstName),
                'last_name' => trim($lastName),
                'email' => \is_string($email) && '' !== trim($email) ? trim($email) : 'CHANGE_ME_admin@example.com',
                'locale' => (string) $locale,
                'password' => $password,
            ];
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $login = \is_string($login) && '' !== trim($login)
            ? trim($login)
            : (string) $this->enterData($helper, $input, $output, 'Admin login name: ', 'Please enter a login name.');

        $firstName = \is_string($firstName) && '' !== trim($firstName)
            ? trim($firstName)
            : (string) $this->enterData($helper, $input, $output, 'User first name: ', 'Please enter user first name.');

        $lastName = \is_string($lastName) && '' !== trim($lastName)
            ? trim($lastName)
            : (string) $this->enterData($helper, $input, $output, 'User last name: ', 'Please enter user last name.');

        $email = \is_string($email) && '' !== trim($email)
            ? trim($email)
            : (string) $this->enterData($helper, $input, $output, 'Admin email (leave empty to change later): ', 'Please enter an email or leave empty.');

        if ('' === $email) {
            $email = 'CHANGE_ME_admin@thelia.net';
        }

        if (!\is_string($password) || '' === trim($password)) {
            while (true) {
                $pass1 = (string) $this->enterData($helper, $input, $output, 'Password: ', 'Please enter a password.', true);
                $pass2 = (string) $this->enterData($helper, $input, $output, 'Password (again): ', 'Please enter the password again.', true);

                if ($pass1 === $pass2 && '' !== $pass1) {
                    $password = $pass1;
                    break;
                }

                $output->writeln('<error>Passwords are different, please try again.</error>');
            }
        }

        return [
            'login_name' => $login,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'locale' => (string) $locale,
            'password' => $password,
        ];
    }

    private function createTheliaCliProcess(array $connectionInfo, array $command): Process
    {
        $process = new Process($command);
        $process->setTimeout(null);

        $env = [
            'DATABASE_HOST' => (string) $connectionInfo['host'],
            'DATABASE_PORT' => (string) $connectionInfo['port'],
            'DATABASE_NAME' => (string) $connectionInfo['dbName'],
            'DATABASE_USER' => (string) $connectionInfo['username'],
            'DATABASE_PASSWORD' => (string) $connectionInfo['password'],
            'APP_ENV' => ($_SERVER['APP_ENV'] ?? 'dev'),
            'APP_DEBUG' => ($_SERVER['APP_DEBUG'] ?? '0'),
        ];

        $process->setEnv($env);

        return $process;
    }
}
