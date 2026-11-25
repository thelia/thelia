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
 * One line of a revision as the shop writes it.
 *
 * A line of the quote the shop starts from carries its id, so that it keeps the catalog price it
 * was written against; a line the shop adds has none, and gets the price the buyer would pay.
 */
final readonly class QuoteDraftLine
{
    public function __construct(
        public ?int $quoteItemId,
        public int $productSaleElementsId,
        public float $quantity,
        public string $price,
    ) {
    }
}
