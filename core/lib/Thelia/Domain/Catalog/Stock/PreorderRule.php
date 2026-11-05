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

/**
 * The single rule that tells a preorder apart: a quantity is on preorder when
 * it goes beyond the physical stock the other carts leave, and the sale
 * element keeps selling at zero. The stock decrement, the payment, the
 * catalogue and the themes all ask this rule.
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

        return $askedQuantity > max(0.0, $physicalQuantity - max(0.0, $reservedByOtherCarts));
    }

    /** Whether the next unit sold would be a preorder, for the catalogue. */
    public static function nextUnitIsPreorder(
        float $physicalQuantity,
        float $reservedByOtherCarts,
        OutOfStockPolicy $policy,
    ): bool {
        return self::isPreorder(1.0, $physicalQuantity, $reservedByOtherCarts, $policy);
    }
}
