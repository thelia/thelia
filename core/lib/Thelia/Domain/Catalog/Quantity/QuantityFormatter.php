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
 * Writes a quantity the way the language reads it, with the decimals of its combination:
 * "1,25 kg" in French, "1.25 kg" in English, "6" for six pieces. Templates call it from the
 * `format_quantity` Twig filter, which the core declares (`Twig\QuantityExtension`).
 */
final readonly class QuantityFormatter
{
    /**
     * A quantity sold carries its unit only when it is sold by measure: a bottle of 0.75 l
     * sold by the piece is written "1", not "1 l", whatever unit its content is given in.
     */
    public function format(float $quantity, int $decimals, ?UnitCode $unitCode = null, ?string $locale = null): string
    {
        if (null === $unitCode || !QuantityScale::isSoldByMeasure($decimals)) {
            return $this->number($quantity, $decimals, $locale);
        }

        return $this->formatMeasure($quantity, $decimals, $unitCode, $locale);
    }

    /**
     * A measure always carries its unit, whatever its decimals: the content of a unit or the
     * reference unit of a price, "100 g", "0,75 l".
     */
    public function formatMeasure(float $quantity, int $decimals, UnitCode $unitCode, ?string $locale = null): string
    {
        return $this->number($quantity, $decimals, $locale).' '.$unitCode->symbol();
    }

    private function number(float $quantity, int $decimals, ?string $locale): string
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

        return $number;
    }
}
