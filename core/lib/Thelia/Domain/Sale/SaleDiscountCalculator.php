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

namespace Thelia\Domain\Sale;

use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Sale;

/**
 * The single place where the discount of a sale operation is applied to a price.
 *
 * The offset a shopkeeper types in the back office is what the customer sees taken
 * off, so it applies to the TAXED price: the untaxed price goes up through the tax
 * rule, the offset comes off there, and the result comes back down. Taking a
 * percentage off the untaxed price instead would not give the same taxed figure.
 *
 * Both the public price write of Thelia\Action\Sale and the reserved price resolved
 * for one customer go through this, so a reserved operation and a public one with
 * the same offset always agree to the cent.
 */
class SaleDiscountCalculator
{
    /**
     * @param float                  $untaxedPrice  the catalog price, excluding tax
     * @param int                    $offsetType    one of the Sale::OFFSET_TYPE_* constants
     * @param float                  $offset        the offset value, an amount or a percentage depending on $offsetType
     * @param TaxCalculatorInterface $taxCalculator a calculator already loaded with the product and the shop country
     *
     * @return float the discounted price, excluding tax
     */
    public function computeUntaxedPromoPrice(
        float $untaxedPrice,
        int $offsetType,
        float $offset,
        TaxCalculatorInterface $taxCalculator,
    ): float {
        $priceWithTax = $taxCalculator->getTaxedPrice($untaxedPrice);

        // An unknown offset type gives no discount at all: a stray value in
        // `sale.price_offset_type` must never hand products out for free.
        $promoPriceWithTax = match ($offsetType) {
            Sale::OFFSET_TYPE_AMOUNT => max(0, $priceWithTax - $offset),
            Sale::OFFSET_TYPE_PERCENTAGE => $priceWithTax * (1 - $offset / 100),
            default => $priceWithTax,
        };

        return (float) $taxCalculator->getUntaxedPrice($promoPriceWithTax);
    }
}
