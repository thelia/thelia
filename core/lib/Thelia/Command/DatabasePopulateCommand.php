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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Core\Install\Database;

/**
 * Applies the core schema (thelia.sql) and reference data (insert.sql)
 * to an existing database.
 *
 * Non-interactive, designed for CI and test environments.
 * Does not write config files, does not create admin users,
 * does not handle modules — use module:schema:apply for that.
 */
#[AsCommand(
    name: 'thelia:database:populate',
    description: 'Apply core schema and reference data to the database',
)]
final class DatabasePopulateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('schema-only', null, InputOption::VALUE_NONE, 'Apply only thelia.sql (schema), skip insert.sql (reference data)')
            ->addOption('seed-only', null, InputOption::VALUE_NONE, 'Apply only insert.sql (reference data), skip thelia.sql (schema)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $schemaOnly = (bool) $input->getOption('schema-only');
        $seedOnly = (bool) $input->getOption('seed-only');

        if ($schemaOnly && $seedOnly) {
            $io->error('Options --schema-only and --seed-only are mutually exclusive.');

            return Command::FAILURE;
        }

        $host = $_SERVER['DATABASE_HOST'] ?? '';
        $port = $_SERVER['DATABASE_PORT'] ?? '3306';
        $user = $_SERVER['DATABASE_USER'] ?? '';
        $password = $_SERVER['DATABASE_PASSWORD'] ?? '';
        $dbName = $_SERVER['DATABASE_NAME'] ?? '';

        if ('' === $host || '' === $dbName) {
            $io->error('DATABASE_HOST and DATABASE_NAME environment variables are required.');

            return Command::FAILURE;
        }

        $dsn = \sprintf('mysql:host=%s;dbname=%s;port=%s', $host, $dbName, $port);

        try {
            $pdo = new \PDO($dsn, $user, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $io->error(\sprintf(
                'Could not connect to database "%s" at %s:%s: %s',
                $dbName,
                $host,
                $port,
                $e->getMessage(),
            ));

            return Command::FAILURE;
        }

        if (!\defined('THELIA_SETUP_DIRECTORY')) {
            $io->error('THELIA_SETUP_DIRECTORY constant is not defined. Ensure core/bootstrap.php is loaded.');

            return Command::FAILURE;
        }

        $database = new Database($pdo);

        $files = [];
        if (!$seedOnly) {
            $schemaFile = THELIA_SETUP_DIRECTORY.'thelia.sql';
            if (!file_exists($schemaFile)) {
                $io->error(\sprintf('Schema file not found: %s', $schemaFile));

                return Command::FAILURE;
            }
            $files[] = $schemaFile;
        }

        if (!$schemaOnly) {
            $insertFile = THELIA_SETUP_DIRECTORY.'insert.sql';
            if (!file_exists($insertFile)) {
                $io->error(\sprintf('Seed file not found: %s', $insertFile));

                return Command::FAILURE;
            }
            $files[] = $insertFile;
        }

        $io->section('Populating database');

        foreach ($files as $file) {
            $io->text(\sprintf('Executing %s...', basename($file)));

            try {
                $database->insertSql(null, [$file]);
            } catch (\RuntimeException $e) {
                $io->error(\sprintf('Failed to execute %s: %s', basename($file), $e->getMessage()));

                return Command::FAILURE;
            }
        }

        $io->success(\sprintf('Database "%s" populated successfully.', $dbName));

        return Command::SUCCESS;
    }
}
