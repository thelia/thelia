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

namespace Thelia\Domain\Catalog\Bundle;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The untaxed price of a bundle priced by its components, in one currency.
 */
#[Exclude]
final readonly class BundlePrice
{
    /**
     * @param string $untaxedFullPrice the sum of the components, six decimals ("600.000000")
     * @param string $untaxedPrice     the sum less the discount of the bundle, never below zero
     */
    public function __construct(
        public string $untaxedFullPrice,
        public string $untaxedPrice,
    ) {
    }

    public function hasDiscount(): bool
    {
        return BundleDecimal::toMicro($this->untaxedPrice) < BundleDecimal::toMicro($this->untaxedFullPrice);
    }
}
