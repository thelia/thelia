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

namespace Thelia\Domain\Quote\DTO;

/**
 * A line of a revision as the sums need it: the price of one unit without taxes, the same price
 * with the taxes of the line, and the quantity.
 */
final readonly class QuoteSumLine
{
    public function __construct(
        public float $unitPrice,
        public float $unitTaxedPrice,
        public float $quantity,
    ) {
    }
}
