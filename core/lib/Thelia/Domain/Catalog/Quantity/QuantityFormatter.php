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
 * Writes a quantity the way the language reads it, with the decimals of its combination
 * and the symbol of its unit: "1,25 kg" in French, "1.25 kg" in English, "6" for six
 * pieces. The themes call it from their own `format_quantity` Twig filter.
 */
final readonly class QuantityFormatter
{
    public function format(float $quantity, int $decimals, ?UnitCode $unitCode = null, ?string $locale = null): string
    {
        $decimals = max(0, min(QuantityScale::MAXIMUM_DECIMALS, $decimals));

        $formatter = new \NumberFormatter($locale ?? QuantityTranslation::locale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        $number = $formatter->format($quantity);

        if (false === $number) {
            $number = number_format($quantity, $decimals, '.', '');
        }

        return null === $unitCode ? $number : $number.' '.$unitCode->symbol();
    }
}
