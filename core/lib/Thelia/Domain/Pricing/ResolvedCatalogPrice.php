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
 * The price the catalog price rules give one sale element, for the visitor and the
 * currency it was resolved for.
 *
 * Untaxed, like every price in `product_price`: the tax and the customer discount are
 * added by the reader the way they are added to any promo price. The rule carried
 * along is the one that had the last word, for the back office and the logs; the
 * end date is when the price stops, for a theme that wants to say so.
 */
#[Exclude]
final readonly class ResolvedCatalogPrice
{
    public function __construct(
        public int $productSaleElementsId,
        public float $untaxedPrice,
        public int $ruleId,
        public bool $displayInitialPrice,
        public ?\DateTimeInterface $validUntil = null,
    ) {
    }
}
