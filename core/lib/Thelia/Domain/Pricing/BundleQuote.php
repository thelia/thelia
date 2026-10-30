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
 * What a composition of a bundle priced by its components costs a visitor: the figures a page
 * shows, settled and taxed the way the line of a cart is.
 *
 * `taxedFullPrice` and `taxedSaving` are null when nothing is struck through: the bundle gives
 * no discount, or the shop asks not to show the price without it.
 */
#[Exclude]
final readonly class BundleQuote
{
    public function __construct(
        public float $untaxedPrice,
        public float $taxedPrice,
        public ?float $untaxedFullPrice,
        public ?float $taxedFullPrice,
        public ?float $untaxedSaving,
        public ?float $taxedSaving,
    ) {
    }
}
