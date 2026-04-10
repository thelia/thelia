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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Applies module SQL schemas (TheliaMain.sql + update/*.sql) to the database.
 *
 * Reproduces what modules do in postActivation() + update(), but as
 * an explicit, non-interactive, idempotent command for CI environments.
 *
 * Each SQL statement is executed individually with error handling:
 * "table/column/index already exists" errors are silently ignored
 * to ensure idempotent re-runs.
 */
#[AsCommand(
    name: 'module:schema:apply',
    description: 'Apply SQL schema for one or all modules',
)]
final class ModuleSchemaApplyCommand extends Command
{
    /** MySQL error codes that are safe to ignore for idempotent execution. */
    private const IGNORABLE_MYSQL_CODES = [
        1050, // Table already exists
        1060, // Duplicate column name
        1061, // Duplicate key name
        1068, // Multiple primary key defined
        1826, // Duplicate FK constraint name (MySQL 8.0+)
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('module', InputArgument::OPTIONAL, 'Module name (e.g. CustomerFamily)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Apply schemas for all modules that have a TheliaMain.sql')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List SQL files that would be executed without executing them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $module */
        $module = $input->getArgument('module');
        $all = (bool) $input->getOption('all');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$module && !$all) {
            $io->error('Provide a module name or use --all.');

            return Command::FAILURE;
        }

        if ($module && $all) {
            $io->error('Provide either a module name or --all, not both.');

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
            $io->error(\sprintf('Could not connect to database: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if ($all) {
            $modules = $this->discoverModules();

            if ([] === $modules) {
                $io->warning('No modules with TheliaMain.sql found.');

                return Command::SUCCESS;
            }

            $io->section(\sprintf('Found %d module(s) with SQL schema', \count($modules)));

            $hasError = false;
            foreach ($modules as $moduleName => $modulePath) {
                if (!$this->applyModuleSchema($io, $pdo, $moduleName, $modulePath, $dryRun)) {
                    $hasError = true;
                }
            }

            return $hasError ? Command::FAILURE : Command::SUCCESS;
        }

        $modulePath = $this->findModulePath($module);
        if (null === $modulePath) {
            $io->error(\sprintf('Module "%s" not found in module directories.', $module));

            return Command::FAILURE;
        }

        if (!file_exists($modulePath.'/Config/TheliaMain.sql')) {
            $io->error(\sprintf('No TheliaMain.sql found for module "%s".', $module));

            return Command::FAILURE;
        }

        return $this->applyModuleSchema($io, $pdo, $module, $modulePath, $dryRun)
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    /**
     * @return array<string, string> moduleName => modulePath
     */
    private function discoverModules(): array
    {
        $modules = [];

        $dirs = [
            \defined('THELIA_MODULE_DIR') ? THELIA_MODULE_DIR : '',
            \defined('THELIA_LOCAL_MODULE_DIR') ? THELIA_LOCAL_MODULE_DIR : '',
        ];

        foreach (array_filter($dirs) as $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }

            $entries = scandir($baseDir);
            if (false === $entries) {
                continue;
            }

            foreach ($entries as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                $modulePath = $baseDir.$entry;
                if (is_dir($modulePath) && file_exists($modulePath.'/Config/TheliaMain.sql')) {
                    $modules[$entry] = $modulePath;
                }
            }
        }

        return $modules;
    }

    private function findModulePath(string $moduleName): ?string
    {
        $dirs = [
            \defined('THELIA_LOCAL_MODULE_DIR') ? THELIA_LOCAL_MODULE_DIR : '',
            \defined('THELIA_MODULE_DIR') ? THELIA_MODULE_DIR : '',
        ];

        foreach (array_filter($dirs) as $baseDir) {
            $path = $baseDir.$moduleName;
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    private function applyModuleSchema(SymfonyStyle $io, \PDO $pdo, string $moduleName, string $modulePath, bool $dryRun): bool
    {
        $files = [];

        $mainSql = $modulePath.'/Config/TheliaMain.sql';
        if (file_exists($mainSql)) {
            $files[] = $mainSql;
        }

        $updateDir = $modulePath.'/Config/update';
        if (is_dir($updateDir)) {
            $updateFiles = glob($updateDir.'/*.sql');
            if (false !== $updateFiles && [] !== $updateFiles) {
                usort($updateFiles, static fn (string $a, string $b): int => version_compare(basename($a, '.sql'), basename($b, '.sql')));
                $files = array_merge($files, $updateFiles);
            }
        }

        if ($dryRun) {
            $io->text(\sprintf('<info>%s</info>: %d file(s)', $moduleName, \count($files)));
            foreach ($files as $file) {
                $io->text(\sprintf('  - %s', basename($file)));
            }

            return true;
        }

        $io->text(\sprintf('<info>%s</info>: applying %d file(s)...', $moduleName, \count($files)));

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (false === $content) {
                $io->error(\sprintf('Cannot read file: %s', $file));

                return false;
            }

            $statements = $this->splitSql($content);
            $skipped = 0;

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ('' === $statement) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    if ($this->isIgnorableError($e)) {
                        ++$skipped;
                        continue;
                    }

                    $io->error(\sprintf(
                        'Error in %s/%s: [%s] %s',
                        $moduleName,
                        basename($file),
                        $e->getCode(),
                        $e->getMessage(),
                    ));

                    return false;
                }
            }

            if ($skipped > 0 && $io->isVerbose()) {
                $io->text(\sprintf('  <comment>%s</comment>: %d statement(s) skipped (already applied)', basename($file), $skipped));
            }
        }

        return true;
    }

    private function isIgnorableError(\PDOException $e): bool
    {
        $mysqlCode = (int) ($e->errorInfo[1] ?? 0);

        if (\in_array($mysqlCode, self::IGNORABLE_MYSQL_CODES, true)) {
            return true;
        }

        // MySQL 1005 "Can't create table" with InnoDB errno 121 = duplicate FK constraint name.
        // Common when TheliaMain.sql and update/*.sql define overlapping constraints.
        if (1005 === $mysqlCode && str_contains($e->getMessage(), 'errno: 121')) {
            return true;
        }

        return false;
    }

    /**
     * Split raw SQL content into individual statements.
     *
     * Handles DELIMITER blocks used by stored procedures/triggers.
     * Same logic as Database::prepareSql() but accessible here.
     *
     * @return string[]
     */
    private function splitSql(string $sql): array
    {
        $sql = str_replace(";',", '-CODE-', $sql);
        $sql = trim($sql);

        preg_match_all('#DELIMITER (.+?)\n(.+?)DELIMITER ;#s', $sql, $m);

        foreach ($m[0] as $k => $v) {
            if ('|' === $m[1][$k]) {
                throw new \RuntimeException('Cannot use "|" as SQL delimiter: '.$v);
            }

            $stored = str_replace([';', $m[1][$k]], ['|', ";\n"], $m[2][$k]);
            $sql = str_replace($v, $stored, $sql);
        }

        $statements = [];
        foreach (explode(";\n", $sql) as $part) {
            $statements[] = str_replace(['-CODE-', '|'], [";',", ';'], $part);
        }

        return $statements;
    }
}
