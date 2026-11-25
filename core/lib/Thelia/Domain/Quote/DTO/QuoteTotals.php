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
 * What a revision comes to: decimal strings with six decimals, in the currency of the quote.
 *
 * The discount is the one that applies, which cannot exceed the lines.
 */
final readonly class QuoteTotals
{
    public function __construct(
        public string $itemsUntaxed,
        public string $itemsTax,
        public string $postageUntaxed,
        public string $postageTax,
        public string $discount,
        public string $totalTaxed,
        public string $totalUntaxed,
    ) {
    }
}
