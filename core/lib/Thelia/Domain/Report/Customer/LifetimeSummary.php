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

namespace Thelia\Domain\Report\Customer;

/**
 * The lifetime figures of a set of customers: what a customer spends on average from the first
 * order on (lifetime value), how many days lie on average between the first and the last order
 * (lifespan), and the share of customers who ordered more than once (repeat rate).
 */
final readonly class LifetimeSummary
{
    public function __construct(
        public int $customerCount,
        public int $orderCount,
        public float $revenue,
        public float $averageLifetimeValue,
        public float $averageLifespanDays,
        public float $repeatRate,
        public float $averageOrderCount,
    ) {
    }
}
