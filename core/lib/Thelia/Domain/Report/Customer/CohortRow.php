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
 * The customers whose first order fell in the same month (or quarter), and what they did in each
 * of the months that followed. A cell is null for a month that has not come yet.
 */
final readonly class CohortRow
{
    /** @param list<CohortCell|null> $cells indexed by the number of months (or quarters) since the first order */
    public function __construct(
        public int $index,
        public string $label,
        public \DateTimeImmutable $start,
        public int $size,
        public array $cells,
    ) {
    }

    /**
     * What the cohort spent per customer up to the last month that has come: its lifetime value so far.
     */
    public function lifetimeValue(): float
    {
        $last = null;

        foreach ($this->cells as $cell) {
            if (null !== $cell) {
                $last = $cell;
            }
        }

        return $last?->cumulativeValuePerCustomer ?? 0.0;
    }
}
