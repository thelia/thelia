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

use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;

/**
 * Arithmetic on the amounts of the payment journal.
 *
 * The column is a DECIMAL(16,6), read and written as a string by Propel, and the
 * rest of the core computes prices as floats. Both are carried here as whole
 * millionths in an integer: a decimal string is parsed digit by digit, never through
 * a float, and a float is rounded to six decimals first. "120.000000" and 120.0 are
 * then the same amount, and a float a hair under its decimal value does not read as
 * smaller.
 *
 * An amount the column cannot hold — more than ten digits before the point — is
 * refused rather than wrapped around: read as millionths it would overflow the
 * integer and come back as a small figure that passes any ceiling.
 */
final class PaymentAmount
{
    public const SCALE = 6;

    /** The largest amount a DECIMAL(16,6) holds, in millionths. */
    private const MAX_UNITS = 9_999_999_999_999_999;

    private function __construct()
    {
    }

    public static function normalize(float|int|string $amount): string
    {
        return self::ofUnits(self::units($amount));
    }

    public static function toFloat(float|int|string $amount): float
    {
        return self::units($amount) / 10 ** self::SCALE;
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
        return self::ofUnits(self::units($left) - self::units($right));
    }

    public static function add(float|int|string $left, float|int|string $right): string
    {
        return self::ofUnits(self::units($left) + self::units($right));
    }

    /**
     * The amount in millionths.
     *
     * @throws InvalidPaymentAmountException when the amount is not a finite number or
     *                                       exceeds what the column holds
     */
    public static function units(float|int|string $amount): int
    {
        if (\is_float($amount)) {
            if (!is_finite($amount)) {
                throw new InvalidPaymentAmountException('A payment amount must be a finite number.');
            }

            $amount = \sprintf('%.'.self::SCALE.'F', $amount);
        }

        $text = trim((string) $amount);

        if (1 !== preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $text, $parts) || ('' === $parts[2] && '' === ($parts[3] ?? ''))) {
            throw new InvalidPaymentAmountException(\sprintf('"%s" is not a payment amount.', $text));
        }

        $integerDigits = ltrim($parts[2], '0');
        $fractionDigits = $parts[3] ?? '';
        $roundUp = \strlen($fractionDigits) > self::SCALE && (int) $fractionDigits[self::SCALE] >= 5;
        $fractionDigits = str_pad(substr($fractionDigits, 0, self::SCALE), self::SCALE, '0');

        // Ten digits before the point is what the column holds: beyond, the integer
        // below would overflow before the comparison could refuse it.
        if (\strlen($integerDigits) > 10) {
            throw self::tooLarge($text);
        }

        $units = (int) (('' === $integerDigits ? '0' : $integerDigits).$fractionDigits) + ($roundUp ? 1 : 0);

        if ($units > self::MAX_UNITS) {
            throw self::tooLarge($text);
        }

        return '-' === $parts[1] ? -$units : $units;
    }

    public static function ofUnits(int $units): string
    {
        $sign = $units < 0 ? '-' : '';
        $digits = str_pad((string) abs($units), self::SCALE + 1, '0', \STR_PAD_LEFT);

        return $sign.substr($digits, 0, -self::SCALE).'.'.substr($digits, -self::SCALE);
    }

    private static function tooLarge(string $amount): InvalidPaymentAmountException
    {
        return new InvalidPaymentAmountException(\sprintf('The payment amount %s exceeds what the journal can hold.', $amount));
    }
}
