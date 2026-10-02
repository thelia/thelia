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
 * Meant for columns an administrator types freely and a person opens in a spreadsheet,
 * such as a manufacturer part number, not for amounts: a negative price starts with a
 * minus sign too.
 */
final class SpreadsheetFormulaGuard
{
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function neutralize(mixed $value): mixed
    {
        if (!\is_string($value) || '' === $value || !\in_array($value[0], self::FORMULA_TRIGGERS, true)) {
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
