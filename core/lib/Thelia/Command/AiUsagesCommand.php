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
use Thelia\Domain\Ai\Service\AiUsageRegister;

/**
 * The register of the AI usages of the shop: module, purpose, audience, range, transparency duty, notice and mark
 * on or off, state of compliance with the AI Act, jurisdiction of the connection. The merchant keeps the export
 * as the record of the AI he runs; the JSON is the one the admin API serves.
 */
#[AsCommand(name: 'thelia:ai:usages', description: 'List the AI usages of the shop with their AI Act compliance status (table, json or csv)')]
class AiUsagesCommand extends ContainerAwareCommand
{
    public const array FORMATS = ['table', 'json', 'csv'];

    private const array COLUMNS = ['id', 'module', 'purpose', 'audience', 'tier', 'transparency', 'notice_enabled', 'provenance_mark_enabled', 'active', 'compliance', 'jurisdiction'];

    public function __construct(private readonly AiUsageRegister $register)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table, json or csv', 'table')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of the compliance messages (default: the default language of the shop)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = (string) $input->getOption('format');

        if (!\in_array($format, self::FORMATS, true)) {
            $io->error(\sprintf('Unknown format "%s": use %s.', $format, implode(', ', self::FORMATS)));

            return Command::INVALID;
        }

        $locale = $input->getOption('locale');
        $rows = $this->register->rows(\is_string($locale) && '' !== $locale ? $locale : null);

        match ($format) {
            'json' => $output->writeln(json_encode($rows, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)),
            'csv' => $output->write($this->csv($rows)),
            default => $this->table($io, $rows),
        };

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function table(SymfonyStyle $io, array $rows): void
    {
        $io->table(self::COLUMNS, array_map(fn (array $row): array => array_map(self::cell(...), $this->line($row)), $rows));
    }

    /**
     * One line per usage; the explanation of what is not met comes last, in its own column.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        \assert(false !== $stream);

        fputcsv($stream, [...self::COLUMNS, 'compliance_explanation'], ',', '"', '');

        foreach ($rows as $row) {
            $explanations = array_map(
                static fn (array $detail): string => $detail['message'],
                array_filter($row['compliance_details'], static fn (array $detail): bool => 'met' !== $detail['state']),
            );

            fputcsv($stream, array_map(self::spreadsheetSafe(...), [...array_map(self::cell(...), $this->line($row)), implode(' ', $explanations)]), ',', '"', '');
        }

        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private function line(array $row): array
    {
        return array_map(static fn (string $column): mixed => $row[$column], self::COLUMNS);
    }

    private static function cell(mixed $value): string
    {
        return match (true) {
            true === $value => 'yes',
            false === $value => 'no',
            null === $value => '',
            default => (string) $value,
        };
    }

    /**
     * A cell that starts with =, +, - or @ is read as a formula by a spreadsheet: the purpose of a usage comes
     * from a module, so it is neutralised.
     */
    private static function spreadsheetSafe(string $cell): string
    {
        return 1 === preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }
}
