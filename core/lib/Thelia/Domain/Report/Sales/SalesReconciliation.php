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
 * How the revenue of a period is made: the taxed order lines, less the discounts (never more
 * than the goods of their order), plus the postage. The lines are what the sales by category
 * and by product share out, and the revenue is the sum of the totals the orders were charged.
 */
final readonly class SalesReconciliation
{
    public function __construct(
        public float $linesTotal,
        public float $discounts,
        public float $postage,
        public float $revenue,
        public int $orderCount,
    ) {
    }
}
