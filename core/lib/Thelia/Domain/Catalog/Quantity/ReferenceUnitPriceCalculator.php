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

use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Model\ProductSaleElements;

/**
 * The price of a combination brought to its reference unit, computed from the price the
 * page shows next to it (taxes and promotion as that page shows them): the caller
 * decides which price that is, this service never does. Never stored.
 */
final readonly class ReferenceUnitPriceCalculator
{
    /**
     * Null when the combination does not say what one unit sold contains, or carries a rule the quantity rules refuse.
     */
    public function compute(float $displayedPrice, ProductSaleElements|QuantityRule $rule): ?ReferenceUnitPrice
    {
        if (!$rule instanceof QuantityRule) {
            try {
                $rule = QuantityRule::fromProductSaleElements($rule);
            } catch (InvalidQuantityRuleException) {
                // A rule written in SQL or by a module that the rules refuse: no reference price, not a failed page.
                return null;
            }
        }

        if (!$rule->hasReferencePrice() || null === $rule->unitQuantity || null === $rule->referenceUnitQuantity || null === $rule->unitCode) {
            return null;
        }

        return new ReferenceUnitPrice(
            MoneyRounding::toTheCent($displayedPrice / $rule->unitQuantity * $rule->referenceUnitQuantity),
            $rule->referenceUnitQuantity,
            $rule->unitCode,
        );
    }
}
