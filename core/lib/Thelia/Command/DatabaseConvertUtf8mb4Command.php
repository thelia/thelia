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

use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Install\Charset\CharacterColumn;
use Thelia\Install\Charset\Utf8mb4Converter;
use Thelia\Install\Charset\Utf8mb4TableConversion;
use Thelia\Model\Map\ConfigTableMap;

/**
 * Converts to utf8mb4 the tables a shop upgraded from Thelia 2 still has in utf8 (utf8mb3),
 * where an emoji is refused while a fresh install accepts it.
 *
 * Each table is rebuilt and locked for writes while it converts, for a time that grows with
 * its size, which is why no update script runs it. Dry-run by default: the list comes first,
 * --force second, during a maintenance window and after a backup.
 */
#[AsCommand(
    name: 'thelia:database:convert-utf8mb4',
    description: 'List, then convert with --force, the tables that are not in utf8mb4',
)]
final class DatabaseConvertUtf8mb4Command extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Convert the tables (default: dry-run, only list them)')
            ->addOption('table', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only this table (repeatable); the database default character set is then left as it is');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        /** @var list<string> $onlyTables */
        $onlyTables = $input->getOption('table');

        $converter = new Utf8mb4Converter(Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME));

        try {
            $conversions = $converter->tablesToConvert($onlyTables);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $databaseCharset = $converter->databaseCharset();
        $convertsDatabaseDefault = [] === $onlyTables && Utf8mb4Converter::CHARSET !== $databaseCharset;

        if ([] === $conversions && !$convertsDatabaseDefault) {
            $io->success([] === $onlyTables ? 'Every table is already in utf8mb4.' : 'These tables are already in utf8mb4.');

            return Command::SUCCESS;
        }

        if ($convertsDatabaseDefault) {
            $io->text(\sprintf('The database creates new tables in %s by default; it will create them in utf8mb4.', $databaseCharset));
        }

        $convertible = array_values(array_filter($conversions, static fn (Utf8mb4TableConversion $conversion): bool => $conversion->isConvertible()));
        $skipped = array_values(array_filter($conversions, static fn (Utf8mb4TableConversion $conversion): bool => null !== $conversion->unsupportedCharset));
        $blocked = array_values(array_filter($conversions, static fn (Utf8mb4TableConversion $conversion): bool => null === $conversion->unsupportedCharset && [] !== $conversion->blockers));

        if ([] !== $convertible) {
            $this->listTables($io, $convertible);
        }

        if ([] !== $skipped) {
            $io->warning(array_merge(
                ['These tables hold another character set than utf8 and are left alone. Their bytes may be UTF-8 written through a latin1 connection, which a conversion would garble: check the data, then convert them by hand.'],
                array_map(static fn (Utf8mb4TableConversion $conversion): string => \sprintf('%s (%s)', $conversion->table, $conversion->unsupportedCharset), $skipped),
            ));
        }

        if ([] !== $blocked) {
            $messages = ['These tables cannot be converted as they are. Nothing was changed; settle each point, or convert the other tables by naming them with --table:'];

            foreach ($blocked as $conversion) {
                foreach ($conversion->blockers as $blocker) {
                    $messages[] = \sprintf('%s: %s', $conversion->table, $blocker);
                }
            }

            $io->error($messages);

            return Command::FAILURE;
        }

        if (!$force) {
            $io->note('Dry run, nothing was changed. Each table is rebuilt and locked for writes while it converts, for a time that grows with its size: back the database up, then run the command again with --force during a maintenance window.');

            return Command::SUCCESS;
        }

        foreach ($convertible as $conversion) {
            $io->write(\sprintf('Converting <info>%s</info>... ', $conversion->table));
            $start = hrtime(true);

            try {
                $converter->convert($conversion);
            } catch (\PDOException $exception) {
                $io->newLine();
                $io->error([
                    \sprintf('Converting table "%s" failed: %s', $conversion->table, $exception->getMessage()),
                    'The tables listed before it are converted and stay so; the others are untouched. Fix the cause, then run the command again: it picks up the tables left.',
                ]);

                return Command::FAILURE;
            }

            $io->writeln(\sprintf('done in %s', Helper::formatTime((hrtime(true) - $start) / 1e9)));
        }

        if ($convertsDatabaseDefault) {
            $converter->convertDatabaseDefault();
        }

        $io->success(\sprintf('%d table(s) converted to utf8mb4.', \count($convertible)));

        return Command::SUCCESS;
    }

    /**
     * @param list<Utf8mb4TableConversion> $conversions
     */
    private function listTables(SymfonyStyle $io, array $conversions): void
    {
        $io->text(\sprintf('%d table(s) to convert, with the columns they change:', \count($conversions)));
        $io->listing(array_map(
            static fn (Utf8mb4TableConversion $conversion): string => \sprintf(
                '<info>%s</info> (%s, about %s rows, %s%s): %s',
                $conversion->table,
                $conversion->collation,
                number_format($conversion->approximateRows),
                Helper::formatMemory($conversion->sizeInBytes),
                $conversion->needsDynamicRowFormat() ? \sprintf(', row format %s -> DYNAMIC', $conversion->rowFormat) : '',
                implode(', ', array_map(
                    static fn (CharacterColumn $column): string => $column->changesCollation()
                        ? \sprintf('%s (%s -> %s)', $column->name, $column->collation, Utf8mb4Converter::COLLATION)
                        : \sprintf('%s (%s)', $column->name, $column->charset),
                    $conversion->changedColumns(),
                )) ?: 'none, only the table default',
            ),
            $conversions,
        ));
    }
}
