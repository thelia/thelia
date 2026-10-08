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

namespace Thelia\Domain\Payment\Service;

/**
 * Arithmetic on the amounts of the payment journal.
 *
 * The column is a DECIMAL(16,6), read and written as a string by Propel, and the
 * rest of the core computes prices as floats. Both are compared here in whole
 * millionths, so that "120.000000" and 120.0 are the same amount and a float that
 * came out of a sum a hair under its decimal value does not read as smaller.
 */
final class PaymentAmount
{
    public const SCALE = 6;

    private const UNIT = 1_000_000;

    private function __construct()
    {
    }

    public static function normalize(float|int|string $amount): string
    {
        return number_format(self::toFloat($amount), self::SCALE, '.', '');
    }

    public static function toFloat(float|int|string $amount): float
    {
        return (float) $amount;
    }

    /**
     * -1, 0 or 1, the way a spaceship operator answers, at the precision of the column.
     */
    public static function compare(float|int|string $left, float|int|string $right): int
    {
        return self::units($left) <=> self::units($right);
    }

    public static function isPositive(float|int|string $amount): bool
    {
        return self::units($amount) > 0;
    }

    public static function subtract(float|int|string $left, float|int|string $right): string
    {
        return self::fromUnits(self::units($left) - self::units($right));
    }

    public static function add(float|int|string $left, float|int|string $right): string
    {
        return self::fromUnits(self::units($left) + self::units($right));
    }

    private static function units(float|int|string $amount): int
    {
        return (int) round(self::toFloat($amount) * self::UNIT);
    }

    private static function fromUnits(int $units): string
    {
        return self::normalize($units / self::UNIT);
    }
}
