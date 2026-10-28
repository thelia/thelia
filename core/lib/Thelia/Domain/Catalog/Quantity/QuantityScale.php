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

namespace Thelia\Domain\Catalog\Quantity;

/**
 * Quantities compared as integers. A quantity is stored with six decimals at most
 * (DECIMAL(16,6) for the rules, DOUBLE for the quantities), so it is read as a whole
 * number of millionths first, then counted in steps of 10^-decimals: two floats are
 * never compared for equality.
 *
 * @internal
 */
final class QuantityScale
{
    public const MAXIMUM_DECIMALS = 3;

    private const MICRO = 1_000_000;

    /**
     * The quantity as a whole number of millionths.
     */
    public static function micro(float $quantity): int
    {
        return (int) round($quantity * self::MICRO);
    }

    /**
     * How many millionths one step of 10^-decimals holds.
     */
    public static function microPerUnit(int $decimals): int
    {
        return intdiv(self::MICRO, 10 ** $decimals);
    }

    /**
     * Whether the quantity carries no more than the given decimals.
     */
    public static function fits(float $quantity, int $decimals): bool
    {
        return 0 === self::micro($quantity) % self::microPerUnit($decimals);
    }

    /**
     * The quantity in units of 10^-decimals, cut down to the decimals it may carry.
     */
    public static function scaledDown(float $quantity, int $decimals): int
    {
        return intdiv(self::micro($quantity), self::microPerUnit($decimals));
    }

    public static function unscaled(int $scaledQuantity, int $decimals): float
    {
        return $scaledQuantity / 10 ** $decimals;
    }
}
