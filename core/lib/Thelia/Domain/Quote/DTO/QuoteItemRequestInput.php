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
 * What a buyer asks for on one line of a revision: another quantity, another unit price without
 * taxes, or both. What is left null stays as the shop proposed it.
 */
final readonly class QuoteItemRequestInput
{
    public function __construct(
        public int $quoteItemId,
        public ?float $quantity = null,
        public ?string $price = null,
    ) {
    }
}
