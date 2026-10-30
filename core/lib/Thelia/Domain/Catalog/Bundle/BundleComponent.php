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
 * What the catalogue says of the sale element a bundle item points at, at the moment of the read.
 */
#[Exclude]
final readonly class BundleComponent
{
    public function __construct(
        public int $productSaleElementsId,
        public int $productId,
        public bool $sellable,
        public bool $virtual,
        public float $availableQuantity,
    ) {
    }
}
