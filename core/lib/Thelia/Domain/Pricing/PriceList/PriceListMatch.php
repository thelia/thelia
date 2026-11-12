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

namespace Thelia\Domain\Pricing\PriceList;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the price list that applies to a customer says about one sale element.
 *
 * A match with a price is the final price: nothing else applies on top of it. A match
 * without one ($purchasable false, $untaxedPrice null) is the answer of an exclusive
 * list that does not price the sale element: it cannot be bought by this customer.
 */
#[Exclude]
final readonly class PriceListMatch
{
    /**
     * @param string|null                                               $untaxedPrice the price without taxes at the quantity asked, six decimals ('80.000000'); null when not purchasable
     * @param int                                                       $minQuantity  the minimum quantity of the line retained, 0 when not purchasable
     * @param array<int, array{minQuantity: int, untaxedPrice: string}> $tiers        every tier of the list retained for the sale element, the smallest minimum quantity first
     */
    public function __construct(
        public int $priceListId,
        public string $priceListCode,
        public ?string $untaxedPrice,
        public bool $purchasable,
        public int $minQuantity,
        public array $tiers,
    ) {
    }
}
