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
 * What a cart may still buy of a sale element, with the figures it comes from: the physical
 * stock it sells from, what the other carts hold of it, and the sale mode of the sale element.
 *
 * The answer of {@see SellableStock}; the preorder verdicts come from {@see PreorderRule}, so
 * that the catalogue, the cart and the checkout never tell a preorder differently.
 */
final readonly class SellableQuantity
{
    /**
     * @param float|null $available the units that may be bought, never below zero; null when there is no limit
     */
    public function __construct(
        public ?float $available,
        public float $physicalQuantity,
        public float $reservedByOtherCarts,
        public OutOfStockPolicy $policy,
    ) {
    }

    /** Whether a line of that many units goes beyond what the other carts leave of the physical stock. */
    public function isPreorderFor(float $askedQuantity): bool
    {
        return PreorderRule::isPreorder($askedQuantity, $this->physicalQuantity, $this->reservedByOtherCarts, $this->policy);
    }

    /**
     * Whether the next unit bought would be a preorder, for the catalogue: nothing physical is
     * left for it, and the sale mode still sells (a limit used up sells nothing).
     */
    public function nextUnitIsPreorder(): bool
    {
        return (null === $this->available || $this->available > 0)
            && PreorderRule::nextUnitIsPreorder($this->physicalQuantity, $this->reservedByOtherCarts, $this->policy);
    }
}
