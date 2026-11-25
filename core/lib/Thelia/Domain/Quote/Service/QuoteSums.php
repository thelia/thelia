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

namespace Thelia\Domain\Quote\Service;

use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Domain\Quote\DTO\QuoteSumLine;
use Thelia\Domain\Quote\DTO\QuoteTotals;

/**
 * The arithmetic of a revision, on amounts that are already worked out: the taxes of a line and of
 * the postage come from the tax engine of the shop, which is not asked here.
 *
 * - A line is totalled as the cart totals it: by the unit price, or over the sum, as the shop
 *   rounds; the lines are then brought to the cent.
 * - The discount is an amount with taxes, taken off the lines with taxes, and never below nothing
 *   nor above them; the postage comes after it.
 * - The share of the discount that is not tax follows the average rate of the lines.
 */
final readonly class QuoteSums
{
    /**
     * @param list<QuoteSumLine>     $lines
     * @param \Closure(float): float $postageTaxOf the tax on the postage without taxes, which is handed over rounded to the cent
     */
    public function totals(array $lines, bool $roundingOfSums, float $discount, float $postage, \Closure $postageTaxOf): QuoteTotals
    {
        $itemsUntaxed = 0.0;
        $itemsTaxed = 0.0;

        foreach ($lines as $line) {
            $itemsUntaxed += $this->lineTotal($line->unitPrice, $line->quantity, $roundingOfSums);
            $itemsTaxed += $this->lineTotal($line->unitTaxedPrice, $line->quantity, $roundingOfSums);
        }

        $itemsUntaxed = MoneyRounding::toTheCent($itemsUntaxed);
        $itemsTaxed = MoneyRounding::toTheCent($itemsTaxed);
        $discount = min(MoneyRounding::toTheCent($discount), $itemsTaxed);
        $postageUntaxed = MoneyRounding::toTheCent($postage);
        $postageTax = $postageTaxOf($postageUntaxed);

        $untaxedDiscount = $itemsTaxed > 0.0 ? $discount * $itemsUntaxed / $itemsTaxed : 0.0;

        return new QuoteTotals(
            $this->amount($itemsUntaxed),
            $this->amount($itemsTaxed - $itemsUntaxed),
            $this->amount($postageUntaxed),
            $this->amount($postageTax),
            $this->amount($discount),
            $this->amount($itemsTaxed - $discount + $postageUntaxed + $postageTax),
            $this->amount($itemsUntaxed - $untaxedDiscount + $postageUntaxed),
        );
    }

    private function lineTotal(float $unitPrice, float $quantity, bool $roundingOfSums): float
    {
        if ($roundingOfSums) {
            return MoneyRounding::toTheCent($unitPrice * $quantity);
        }

        return MoneyRounding::toTheCent($unitPrice) * $quantity;
    }

    private function amount(float $value): string
    {
        return number_format(round($value, 6), 6, '.', '');
    }
}
