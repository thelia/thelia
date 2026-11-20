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

namespace Thelia\Domain\Company\Checkout\Service;

/**
 * Amounts of the default currency as the DECIMAL(16,6) columns hold them, added and written
 * without ever going through a float.
 */
final class SpendingAmount
{
    private const int SCALE = 1_000_000;

    /**
     * A float amount of the cart, as the column would hold it.
     */
    public static function fromFloat(float $amount): string
    {
        return number_format(round($amount, 6), 6, '.', '');
    }

    public static function add(string $left, string $right): string
    {
        $sum = self::toMicros($left) + self::toMicros($right);

        return intdiv($sum, self::SCALE).'.'.str_pad((string) ($sum % self::SCALE), 6, '0', \STR_PAD_LEFT);
    }

    /**
     * The amount as a buyer reads it: no trailing zeros, two decimals when there are any.
     */
    public static function display(string $amount): string
    {
        if (!str_contains($amount, '.')) {
            return $amount;
        }

        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return 1 === preg_match('/\.\d$/', $trimmed) ? $trimmed.'0' : $trimmed;
    }

    private static function toMicros(string $amount): int
    {
        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $integer * self::SCALE) + (int) str_pad(substr($fraction, 0, 6), 6, '0');
    }
}
