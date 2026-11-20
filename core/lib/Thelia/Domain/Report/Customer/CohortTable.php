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
 * The cohort table: a row per month (or quarter) of first order, oldest first, a column per month
 * (or quarter) elapsed since, column 0 being the month of the first order.
 */
final readonly class CohortTable
{
    /** @param list<CohortRow> $rows */
    public function __construct(
        public CohortGranularity $granularity,
        public array $rows,
        public int $columnCount,
    ) {
    }
}
