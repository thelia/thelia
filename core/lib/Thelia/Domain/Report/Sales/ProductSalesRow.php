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

namespace Thelia\Domain\Report\Sales;

/**
 * The sales of a product over a period, told apart by the reference the order lines carry:
 * the product may have been deleted since.
 */
final readonly class ProductSalesRow
{
    public function __construct(
        public ?int $productId,
        public string $ref,
        public string $title,
        public int $quantity,
        public float $revenue,
        public float $share,
        public float $previousRevenue,
    ) {
    }
}
