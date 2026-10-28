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

namespace Thelia\Domain\Ai\Internal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * Money of the AI layer as integers: a decimal string is read at a given scale and written back from it, so
 * that nothing is added or compared as a float and no extension is needed. At the scale of the log (8
 * decimals) an amount is below 10^10 and its units stay under the limit of a 64-bit integer.
 *
 * @internal
 */
#[Exclude]
final class AiDecimal
{
    /** The decimals of `ai_usage_log.cost`. */
    public const int SCALE = 8;

    private const int INTEGER_DIGITS = 10;

    private function __construct()
    {
    }

    /**
     * @return int the value in units of 10^-$scale, rounded half up
     *
     * @throws InvalidAiArgumentException when the value is not a non-negative decimal written with a point
     */
    public static function parse(string $value, int $scale = self::SCALE): int
    {
        if (1 !== preg_match('/^(\d{1,'.self::INTEGER_DIGITS.'})(?:\.(\d+))?$/', trim($value), $matches)) {
            throw new InvalidAiArgumentException(\sprintf('"%s" is not a non-negative decimal.', $value));
        }

        $fraction = $matches[2] ?? '';
        $units = (int) $matches[1] * 10 ** $scale + (int) substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return \strlen($fraction) > $scale && $fraction[$scale] >= '5' ? $units + 1 : $units;
    }

    public static function format(int $units, int $scale = self::SCALE): string
    {
        $factor = 10 ** $scale;

        return \sprintf('%d.%0'.$scale.'d', intdiv($units, $factor), $units % $factor);
    }
}
