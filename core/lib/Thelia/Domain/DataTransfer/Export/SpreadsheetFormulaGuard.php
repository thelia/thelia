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

namespace Thelia\Domain\DataTransfer\Export;

/**
 * Keeps a text a spreadsheet would run as a formula from being run when the export is
 * opened: a leading quote makes it a plain text cell again.
 *
 * A plain number keeps its sign: `-5.00` or `+33612345678` cannot call anything, and a
 * quote would turn a negative amount into text.
 */
final class SpreadsheetFormulaGuard
{
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    private const string PLAIN_NUMBER = '/^[+-]?[0-9]+(?:[.,][0-9]+)?$/D';

    public static function neutralize(mixed $value): mixed
    {
        if (!\is_string($value) || '' === $value || !\in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return $value;
        }

        if (1 === preg_match(self::PLAIN_NUMBER, $value)) {
            return $value;
        }

        return "'".$value;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $columns
     *
     * @return array<string, mixed>
     */
    public static function neutralizeColumns(array $row, array $columns): array
    {
        foreach ($columns as $column) {
            if (\array_key_exists($column, $row)) {
                $row[$column] = self::neutralize($row[$column]);
            }
        }

        return $row;
    }
}
