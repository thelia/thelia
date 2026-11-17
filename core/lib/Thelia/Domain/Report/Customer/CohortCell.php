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
 * One month (or quarter) in the life of a cohort: how many of its customers ordered in it, their
 * share of the cohort, and what the cohort spent from its first order to the end of it, per customer.
 */
final readonly class CohortCell
{
    public function __construct(
        public int $offset,
        public int $activeCustomers,
        public float $share,
        public float $revenue,
        public float $cumulativeRevenue,
        public float $cumulativeValuePerCustomer,
    ) {
    }
}
