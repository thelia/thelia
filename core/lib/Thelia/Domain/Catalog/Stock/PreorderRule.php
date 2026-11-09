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

namespace Thelia\Domain\Catalog\Stock;

use Thelia\Domain\Catalog\Quantity\QuantityScale;

/**
 * The single rule that tells a preorder apart: a quantity is on preorder when
 * it goes beyond the physical stock the other carts leave, and the sale
 * element keeps selling at zero. The stock decrement, the payment, the
 * catalogue and the themes all ask this rule.
 *
 * Quantities are compared on the millionth they are stored to ({@see QuantityScale}):
 * 0.7 kg less 0.4 kg held by other carts leaves the 0.3 kg a buyer asks, though the
 * binary difference is 0.29999999999999993.
 */
final class PreorderRule
{
    /**
     * A physical stock of 3 that other carts hold 3 of leaves nothing: asking 2
     * is a preorder under a policy that sells at zero, a refusal otherwise.
     */
    public static function isPreorder(
        float $askedQuantity,
        float $physicalQuantity,
        float $reservedByOtherCarts,
        OutOfStockPolicy $policy,
    ): bool {
        if (OutOfStockPolicy::Refuse === $policy || $askedQuantity <= 0) {
            return false;
        }

        return QuantityScale::micro($askedQuantity) > self::physicallyLeftInMillionths($physicalQuantity, $reservedByOtherCarts);
    }

    /**
     * Whether the next unit sold would be a preorder, for the catalogue: nothing physical is left
     * once the other carts have taken theirs. A stock sold by the piece is whole, so this is the
     * next piece; 0.5 kg left of a quantity sold by measure is still on the shelf.
     */
    public static function nextUnitIsPreorder(
        float $physicalQuantity,
        float $reservedByOtherCarts,
        OutOfStockPolicy $policy,
    ): bool {
        return OutOfStockPolicy::Refuse !== $policy && 0 === self::physicallyLeftInMillionths($physicalQuantity, $reservedByOtherCarts);
    }

    private static function physicallyLeftInMillionths(float $physicalQuantity, float $reservedByOtherCarts): int
    {
        return max(0, QuantityScale::micro($physicalQuantity) - max(0, QuantityScale::micro($reservedByOtherCarts)));
    }
}
