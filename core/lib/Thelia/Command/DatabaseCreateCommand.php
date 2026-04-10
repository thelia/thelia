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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the Thelia database if it does not exist.
 *
 * Designed for CI and test environments: non-interactive, idempotent,
 * reads connection info from environment variables only.
 */
#[AsCommand(
    name: 'thelia:database:create',
    description: 'Create the Thelia database if it does not exist',
)]
final class DatabaseCreateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $host = $_SERVER['DATABASE_HOST'] ?? '';
        $port = $_SERVER['DATABASE_PORT'] ?? '3306';
        $user = $_SERVER['DATABASE_USER'] ?? '';
        $password = $_SERVER['DATABASE_PASSWORD'] ?? '';
        $dbName = $_SERVER['DATABASE_NAME'] ?? '';

        if ('' === $host || '' === $dbName) {
            $io->error('DATABASE_HOST and DATABASE_NAME environment variables are required.');

            return Command::FAILURE;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $dbName)) {
            $io->error(\sprintf('DATABASE_NAME contains invalid characters: "%s"', $dbName));

            return Command::FAILURE;
        }

        $dsn = \sprintf('mysql:host=%s;port=%s', $host, $port);

        try {
            $pdo = new \PDO($dsn, $user, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $io->error(\sprintf(
                'Could not connect to MySQL at %s:%s as "%s": %s',
                $host,
                $port,
                $user,
                $e->getMessage(),
            ));

            return Command::FAILURE;
        }

        $pdo->exec(\sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $dbName,
        ));

        $io->success(\sprintf('Database "%s" is ready.', $dbName));

        return Command::SUCCESS;
    }
}
