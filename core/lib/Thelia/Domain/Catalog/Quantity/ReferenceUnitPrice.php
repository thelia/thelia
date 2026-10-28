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

namespace Thelia\Domain\Catalog\Quantity;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A price for a reference quantity of a unit: 13.32 for 1 litre.
 */
#[Exclude]
final readonly class ReferenceUnitPrice
{
    public function __construct(
        public float $price,
        public float $referenceUnitQuantity,
        public UnitCode $unitCode,
    ) {
    }
}
