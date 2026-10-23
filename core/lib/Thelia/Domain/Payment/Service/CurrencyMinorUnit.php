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
 * How many decimals a currency counts, read from its ISO 4217 code through intl: two
 * for the euro, none for the yen, three for the Kuwaiti dinar.
 *
 * A capture is asked in what the provider can take, never in a fraction of the smallest
 * coin: the journal would then record 10.005 while the bank took 10.00 or 10.01.
 */
final class CurrencyMinorUnit
{
    private const DEFAULT_DECIMALS = 2;

    /** @var array<string, int> */
    private static array $decimals = [];

    private function __construct()
    {
    }

    public static function decimalsOf(?string $isoCode): int
    {
        $isoCode = strtoupper(trim((string) $isoCode));

        if ('' === $isoCode || !class_exists(\NumberFormatter::class)) {
            return self::DEFAULT_DECIMALS;
        }

        if (!isset(self::$decimals[$isoCode])) {
            $formatter = new \NumberFormatter('en@currency='.$isoCode, \NumberFormatter::CURRENCY);
            $digits = $formatter->getAttribute(\NumberFormatter::FRACTION_DIGITS);

            self::$decimals[$isoCode] = \is_int($digits) && $digits >= 0 ? $digits : self::DEFAULT_DECIMALS;
        }

        return self::$decimals[$isoCode];
    }

    /**
     * Whether the amount is a whole number of the smallest coin of the currency.
     */
    public static function fits(float|int|string $amount, ?string $isoCode): bool
    {
        return 0 === PaymentAmount::units($amount) % self::smallestCoinInUnits($isoCode);
    }

    /**
     * Whether what is left is less than the smallest coin, and so nothing a provider
     * could still take.
     */
    public static function isBelowSmallestCoin(float|int|string $amount, ?string $isoCode): bool
    {
        return PaymentAmount::units($amount) < self::smallestCoinInUnits($isoCode);
    }

    /**
     * The amount rounded down to the smallest coin: what can be offered to capture
     * without ever going past what is held.
     */
    public static function floor(float|int|string $amount, ?string $isoCode): string
    {
        $units = PaymentAmount::units($amount);
        $coin = self::smallestCoinInUnits($isoCode);

        return PaymentAmount::ofUnits(intdiv($units, $coin) * $coin);
    }

    private static function smallestCoinInUnits(?string $isoCode): int
    {
        return 10 ** (PaymentAmount::SCALE - min(PaymentAmount::SCALE, self::decimalsOf($isoCode)));
    }
}
