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

namespace Thelia\Domain\Ai\Service;

/**
 * The register of the AI usages written out: the JSON the admin API serves, and the CSV a merchant keeps as the
 * record of the AI he runs. The console command and the back-office export both come here, so they give the same
 * lines as {@see AiUsageRegister::rows()}.
 *
 * @internal
 */
final readonly class AiUsageRegisterExport
{
    public const array COLUMNS = ['id', 'module', 'purpose', 'audience', 'tier', 'transparency', 'notice_enabled', 'provenance_mark_enabled', 'active', 'compliance', 'jurisdiction'];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function json(array $rows): string
    {
        return json_encode($rows, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * One line per usage; the explanation of what is not met comes last, in its own column.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        \assert(false !== $stream);

        fputcsv($stream, [...self::COLUMNS, 'compliance_explanation'], ',', '"', '');

        foreach ($rows as $row) {
            $explanations = array_map(
                static fn (array $detail): string => $detail['message'],
                array_filter($row['compliance_details'], static fn (array $detail): bool => 'met' !== $detail['state']),
            );

            fputcsv($stream, array_map(self::spreadsheetSafe(...), [...$this->cells($row), implode(' ', $explanations)]), ',', '"', '');
        }

        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string> the columns of a row as text: yes, no, empty for null
     */
    public function cells(array $row): array
    {
        return array_map(static fn (string $column): string => match (true) {
            true === $row[$column] => 'yes',
            false === $row[$column] => 'no',
            null === $row[$column] => '',
            default => (string) $row[$column],
        }, self::COLUMNS);
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
