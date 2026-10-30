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

namespace Thelia\Domain\Catalog\Bundle;

/**
 * Decimal arithmetic on whole millionths, the scale of the DECIMAL(16,6) columns. The
 * strings it handles never go through a float, so 0.1 + 0.2 is 0.3.
 *
 * @internal
 */
final class BundleDecimal
{
    public const SCALE = 1_000_000;

    /**
     * The largest whole part a value may have: times the scale it stays under PHP_INT_MAX, so
     * no multiplication here overflows into a float whatever a caller sends.
     */
    public const MAX_WHOLE = 1_000_000_000_000;

    /**
     * The largest amount a DECIMAL(16,6) column holds, in millionths.
     */
    public const MAX_STORED = 9_999_999_999_999_999;

    private const PATTERN = '/^(\d{1,12})(?:\.(\d{1,6}))?$/D';

    private function __construct()
    {
    }

    /**
     * A non-negative decimal with at most six decimals, without trailing zeros: "2", "0.5".
     *
     * @throws \InvalidArgumentException
     */
    public static function normalize(string|int|float $value): string
    {
        return self::fromMicro(self::toMicro($value));
    }

    /**
     * @throws \InvalidArgumentException a value that is not a non-negative decimal of at most six decimals and twelve whole digits
     */
    public static function toMicro(string|int|float $value): int
    {
        if (\is_float($value)) {
            if (!is_finite($value) || $value < 0 || $value >= self::MAX_WHOLE) {
                throw new \InvalidArgumentException('A bundle quantity or price is a finite, non-negative number below 10^12.');
            }

            return (int) round($value * self::SCALE);
        }

        $text = trim((string) $value);

        if (1 !== preg_match(self::PATTERN, $text, $matches)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a non-negative decimal of at most twelve whole digits and six decimals.', mb_strimwidth($text, 0, 40, '...')));
        }

        return (int) $matches[1] * self::SCALE + (int) str_pad($matches[2] ?? '', 6, '0');
    }

    /**
     * The shortest decimal form: "2", "0.5".
     */
    public static function fromMicro(int $micro): string
    {
        $text = self::format($micro);

        return rtrim(rtrim($text, '0'), '.');
    }

    /**
     * Six decimals, the form of a stored price: "540.000000".
     */
    public static function format(int $micro): string
    {
        $sign = $micro < 0 ? '-' : '';
        $micro = abs($micro);

        return \sprintf('%s%d.%06d', $sign, intdiv($micro, self::SCALE), $micro % self::SCALE);
    }

    /**
     * @param int $priceMicro    a price, in millionths
     * @param int $quantityMicro a quantity, in millionths
     *
     * @return int the line total, in millionths, rounded half up
     */
    public static function multiply(int $priceMicro, int $quantityMicro): int
    {
        $whole = intdiv($quantityMicro, self::SCALE);
        $fraction = $quantityMicro % self::SCALE;

        $total = self::guard($priceMicro * $whole) + self::roundedDivision(self::guard($priceMicro * $fraction), self::SCALE);

        return self::guard($total);
    }

    private static function roundedDivision(int $dividend, int $divisor): int
    {
        return intdiv($dividend + intdiv($divisor, 2), $divisor);
    }

    private static function guard(int|float $value): int
    {
        if (!\is_int($value)) {
            throw new \OverflowException('The amount of the bundle exceeds the supported range.');
        }

        return $value;
    }
}
