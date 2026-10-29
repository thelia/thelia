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

namespace Thelia\Domain\Pricing;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a reader shows once an effective price has been merged with the catalog one:
 * the single answer of EffectivePrice::settle(), so that no reader decides alone which
 * of the catalog price and the effective price stands.
 */
#[Exclude]
final readonly class SettledPrice
{
    public function __construct(
        public float $untaxedPrice,
        public float $untaxedPromoPrice,
        public bool $isPromo,
        public bool $displayInitialPrice,
        public bool $priceReplaced,
    ) {
    }

    /**
     * What the price strikes through saves, untaxed: the price without the offer less the price
     * charged. Null when nothing is struck through: not on offer, or an offer the shop asks
     * not to show.
     */
    public function untaxedSaving(): ?float
    {
        return $this->isPromo && $this->displayInitialPrice
            ? round(max(0.0, $this->untaxedPrice - $this->untaxedPromoPrice), 6)
            : null;
    }

    /**
     * The same saving, taxes included, by the taxation of the product the price is the one of:
     * the difference of the two prices taxed, so that the rule is the caller's and is applied
     * once, as it is to the prices shown.
     *
     * @param \Closure(float): float $taxed the untaxed price of the product, taxed
     */
    public function taxedSaving(\Closure $taxed): ?float
    {
        return null === $this->untaxedSaving()
            ? null
            : round(max(0.0, $taxed($this->untaxedPrice) - $taxed($this->untaxedPromoPrice)), 2);
    }
}
