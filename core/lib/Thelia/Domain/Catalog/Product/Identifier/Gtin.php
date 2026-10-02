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

namespace Thelia\Domain\Catalog\Product\Identifier;

/**
 * The GS1 family of trade item numbers a combination carries in `ean_code`: EAN-8, UPC-A,
 * EAN-13 (ISBN-13 included) and GTIN-14. The length tells which one it is, so a single
 * column holds them all.
 *
 * A code is always handled as a string: a numeric cast eats the leading zero of a UPC-A
 * written as an EAN-13, and the code no longer matches the barcode on the box.
 */
final class Gtin
{
    /** @var list<int> */
    public const array LENGTHS = [8, 12, 13, 14];

    /**
     * Drops what people type between the digit groups, as printed under a barcode.
     */
    public static function normalize(string $code): string
    {
        return (string) preg_replace('/[\s\-]+/u', '', $code);
    }

    /**
     * Null when the normalized code is a GTIN; otherwise the first rule it breaks.
     */
    public static function violationOf(string $normalizedCode): ?GtinViolation
    {
        if (1 !== preg_match('/^\d+$/', $normalizedCode)) {
            return GtinViolation::NotDigits;
        }

        if (!\in_array(\strlen($normalizedCode), self::LENGTHS, true)) {
            return GtinViolation::Length;
        }

        if (self::checkDigitOf(substr($normalizedCode, 0, -1)) !== (int) substr($normalizedCode, -1)) {
            return GtinViolation::CheckDigit;
        }

        return null;
    }

    /**
     * GS1 modulo 10: from the right, the digits weigh 3, 1, 3, 1… and the check digit
     * brings the sum to the next multiple of ten.
     */
    public static function checkDigitOf(string $digitsWithoutCheck): int
    {
        $sum = 0;
        $weight = 3;

        for ($position = \strlen($digitsWithoutCheck) - 1; $position >= 0; --$position) {
            $sum += (int) $digitsWithoutCheck[$position] * $weight;
            $weight = 4 - $weight;
        }

        return (10 - $sum % 10) % 10;
    }
}
