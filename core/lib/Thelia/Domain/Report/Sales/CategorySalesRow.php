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
 * The sales of a category over a period, its subcategories included. The amounts are the
 * taxed totals of the order lines, each rounded by the rule of its order, before discount
 * and postage, and summed whatever the currency of the order.
 */
final readonly class CategorySalesRow
{
    public function __construct(
        public CategoryRowKind $kind,
        public ?int $categoryId,
        public ?int $parentId,
        public string $title,
        public int $depth,
        public float $revenue,
        public float $ownRevenue,
        public int $quantity,
        public int $orderCount,
        public float $share,
        public float $previousRevenue,
    ) {
    }
}
