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

namespace Thelia\Domain\Company\Service;

/**
 * An amount stored as a DECIMAL string, compared without turning it into a float.
 */
final class CompanyAmount
{
    /** What the column holds: ten digits, six decimals. */
    private const string PATTERN = '/^\d{1,10}(\.\d{1,6})?$/';

    public static function isValid(string $amount): bool
    {
        return 1 === preg_match(self::PATTERN, $amount);
    }

    /**
     * @return int negative when $left is the smaller amount, zero when they are equal, positive otherwise
     */
    public static function compare(string $left, string $right): int
    {
        [$leftInteger, $leftFraction] = self::split($left);
        [$rightInteger, $rightFraction] = self::split($right);

        return [\strlen($leftInteger), $leftInteger, $leftFraction] <=> [\strlen($rightInteger), $rightInteger, $rightFraction];
    }

    /**
     * @return array{string, string} the integer part without its leading zeros, the fraction on six digits
     */
    private static function split(string $amount): array
    {
        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return [ltrim($integer, '0') ?: '0', str_pad($fraction, 6, '0')];
    }
}
