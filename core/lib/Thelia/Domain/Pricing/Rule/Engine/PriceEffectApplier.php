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

namespace Thelia\Domain\Pricing\Rule\Engine;

use Thelia\Domain\Sale\SaleDiscountCalculator;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Sale;

/**
 * Applies one effect to one untaxed price.
 *
 * The values a merchant types are what the customer sees taken off or pays, so they
 * apply to the TAXED price and the result comes back down through the same tax rule
 * - the arithmetic the flash sales already use, shared to the cent through
 * {@see SaleDiscountCalculator}. A fixed price is a taxed price for the same reason.
 */
final class PriceEffectApplier
{
    public function __construct(private readonly SaleDiscountCalculator $saleDiscountCalculator = new SaleDiscountCalculator())
    {
    }

    /**
     * The untaxed price once the effect has applied, floored at zero.
     */
    public function apply(float $untaxedPrice, RuleEffect $effect, TaxCalculatorInterface $taxCalculator): float
    {
        $result = match ($effect->type) {
            EffectType::Percentage => $this->saleDiscountCalculator->computeUntaxedPromoPrice($untaxedPrice, Sale::OFFSET_TYPE_PERCENTAGE, $effect->value, $taxCalculator),
            EffectType::Amount => $this->saleDiscountCalculator->computeUntaxedPromoPrice($untaxedPrice, Sale::OFFSET_TYPE_AMOUNT, $effect->value, $taxCalculator),
            EffectType::FixedPrice => (float) $taxCalculator->getUntaxedPrice($effect->value),
        };

        return max(0.0, $result);
    }

    /**
     * Whether applying the effect would have taken the price below zero: an amount
     * larger than the taxed price, a negative fixed price, or a percentage above a
     * hundred. The price is floored either way; this is what tells the writer to say so.
     */
    public function wouldGoNegative(float $untaxedPrice, RuleEffect $effect, TaxCalculatorInterface $taxCalculator): bool
    {
        return match ($effect->type) {
            EffectType::Percentage => $effect->value > 100.0,
            EffectType::Amount => (float) $taxCalculator->getTaxedPrice($untaxedPrice) - $effect->value < 0.0,
            EffectType::FixedPrice => $effect->value < 0.0,
        };
    }
}
