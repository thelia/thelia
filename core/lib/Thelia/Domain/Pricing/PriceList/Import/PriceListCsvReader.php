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

namespace Thelia\Domain\Pricing\PriceList\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the CSV of a price list import, and refuses what is not one before reading a line.
 *
 * The file is a regular file, readable, not a symbolic link, not under the public directory, at
 * most MAX_FILE_BYTES; the bytes read are counted as well, so a file that grows under the reader
 * does not escape the bound. The delimiter is a semicolon or a comma, read from the header. The
 * header carries known columns only, each once, a sale element column (pse_ref or pse_id) and an
 * amount column (price or discount_percent).
 */
final readonly class PriceListCsvReader
{
    public const int MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const string COLUMN_REFERENCE = 'pse_ref';
    public const string COLUMN_ID = 'pse_id';
    public const string COLUMN_MINIMUM_QUANTITY = 'min_quantity';
    public const string COLUMN_PRICE = 'price';
    public const string COLUMN_DISCOUNT_PERCENT = 'discount_percent';

    private const array COLUMNS = [
        self::COLUMN_REFERENCE,
        self::COLUMN_ID,
        self::COLUMN_MINIMUM_QUANTITY,
        self::COLUMN_PRICE,
        self::COLUMN_DISCOUNT_PERCENT,
    ];

    private const int MAX_LINE_BYTES = 8192;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDirectory,
    ) {
    }

    /**
     * @return \Generator<int, PriceListCsvRow>
     *
     * @throws PriceListImportException when the file as a whole is refused
     */
    public function read(\SplFileInfo $file): \Generator
    {
        $path = $this->acceptedPath($file);
        $handle = fopen($path, 'r');

        if (false === $handle) {
            throw new PriceListImportException('The file cannot be opened.');
        }

        try {
            $firstLine = fgets($handle, self::MAX_LINE_BYTES);

            if (false === $firstLine) {
                throw new PriceListImportException('The file is empty.');
            }

            $firstLine = $this->withoutByteOrderMark($firstLine);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $columns = $this->columns(str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', ''));
            $line = 1;

            while (false !== ($cells = fgetcsv($handle, self::MAX_LINE_BYTES, $delimiter, '"', ''))) {
                ++$line;

                if ((int) ftell($handle) > self::MAX_FILE_BYTES) {
                    throw new PriceListImportException('The file is larger than 5 MB.');
                }

                if ([null] === $cells) {
                    continue;
                }

                yield $line => $this->row($line, $columns, $cells);
            }
        } finally {
            fclose($handle);
        }
    }

    private function acceptedPath(\SplFileInfo $file): string
    {
        $path = $file->getPathname();

        if (is_link($path)) {
            throw new PriceListImportException('The file is a symbolic link.');
        }

        $realPath = realpath($path);

        if (false === $realPath || !is_file($realPath) || !is_readable($realPath)) {
            throw new PriceListImportException('The file does not exist or cannot be read.');
        }

        $publicDirectory = realpath($this->projectDirectory.'/public');

        if (false !== $publicDirectory && str_starts_with($realPath, $publicDirectory.\DIRECTORY_SEPARATOR)) {
            throw new PriceListImportException('The file is in the public directory.');
        }

        $size = filesize($realPath);

        if (false === $size || $size > self::MAX_FILE_BYTES) {
            throw new PriceListImportException('The file is larger than 5 MB.');
        }

        return $realPath;
    }

    private function withoutByteOrderMark(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    /**
     * @param list<string|null> $header
     *
     * @return list<string>
     */
    private function columns(array $header): array
    {
        $columns = array_map(static fn (?string $cell): string => strtolower(trim((string) $cell)), $header);
        $unknown = array_values(array_diff($columns, self::COLUMNS));

        if ([] !== $unknown) {
            throw new PriceListImportException(\sprintf('Unknown column(s): %s. Expected: %s.', $this->printable($unknown), implode(', ', self::COLUMNS)));
        }

        if (\count($columns) !== \count(array_unique($columns))) {
            throw new PriceListImportException('A column is given twice.');
        }

        if (!\in_array(self::COLUMN_REFERENCE, $columns, true) && !\in_array(self::COLUMN_ID, $columns, true)) {
            throw new PriceListImportException('The header needs pse_ref or pse_id.');
        }

        if (!\in_array(self::COLUMN_PRICE, $columns, true) && !\in_array(self::COLUMN_DISCOUNT_PERCENT, $columns, true)) {
            throw new PriceListImportException('The header needs price or discount_percent.');
        }

        return $columns;
    }

    /**
     * @param list<string>      $columns
     * @param list<string|null> $cells
     */
    private function row(int $line, array $columns, array $cells): PriceListCsvRow
    {
        if (\count($cells) !== \count($columns)) {
            return $this->failed($line, PriceListImportLineError::WRONG_COLUMN_COUNT);
        }

        $values = [];

        foreach ($columns as $position => $column) {
            $cell = trim((string) $cells[$position]);

            if (!mb_check_encoding($cell, 'UTF-8')) {
                return $this->failed($line, PriceListImportLineError::INVALID_ENCODING);
            }

            $values[$column] = '' === $cell ? null : $cell;
        }

        return new PriceListCsvRow(
            $line,
            $values[self::COLUMN_REFERENCE] ?? null,
            $values[self::COLUMN_ID] ?? null,
            $values[self::COLUMN_MINIMUM_QUANTITY] ?? null,
            $values[self::COLUMN_PRICE] ?? null,
            $values[self::COLUMN_DISCOUNT_PERCENT] ?? null,
        );
    }

    private function failed(int $line, string $reason): PriceListCsvRow
    {
        return new PriceListCsvRow($line, null, null, null, null, null, new PriceListImportLineError($line, $reason));
    }

    /**
     * @param list<string> $columns
     */
    private function printable(array $columns): string
    {
        return implode(', ', array_map(static fn (string $column): string => mb_strimwidth(preg_replace('/[^\x20-\x7E]/', '?', $column) ?? '', 0, 40, '...'), \array_slice($columns, 0, 5)));
    }
}
