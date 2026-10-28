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

namespace Thelia\Domain\Pricing\CustomerPrice;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a customer group pays for one sale element in one currency, untaxed. Either
 * price may be absent: the group then has no price of its own for it, and the
 * catalog stands in.
 */
#[Exclude]
final readonly class CustomerGroupPrice
{
    public function __construct(
        public int $productSaleElementsId,
        public ?float $untaxedPrice,
        public ?float $untaxedPromoPrice,
    ) {
    }
}
