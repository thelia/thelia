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
 * The unit of time of a cohort table: a cohort gathers the customers whose first order fell in
 * the same month, or in the same quarter for a shop with few orders.
 */
enum CohortGranularity: string
{
    case Month = 'month';
    case Quarter = 'quarter';

    public function months(): int
    {
        return self::Quarter === $this ? 3 : 1;
    }

    public function perYear(): int
    {
        return intdiv(12, $this->months());
    }

    /**
     * The rank of the month or the quarter of a date column, counted from year 0: the difference
     * of two ranks is the number of months or quarters between them.
     */
    public function indexSql(string $dateColumn): string
    {
        return self::Quarter === $this
            ? '(YEAR('.$dateColumn.') * 4 + QUARTER('.$dateColumn.') - 1)'
            : '(YEAR('.$dateColumn.') * 12 + MONTH('.$dateColumn.') - 1)';
    }

    public function indexOf(\DateTimeInterface $date): int
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return self::Quarter === $this ? $year * 4 + intdiv($month - 1, 3) : $year * 12 + $month - 1;
    }

    /**
     * The first day of the month or of the quarter of the given rank.
     */
    public function startOf(int $index): \DateTimeImmutable
    {
        $year = intdiv($index, $this->perYear());
        $month = ($index % $this->perYear()) * $this->months() + 1;

        return new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $year, $month));
    }

    /**
     * How a cohort is named in a table and in a file: 2026-01 for a month, 2026-Q1 for a quarter.
     */
    public function label(int $index): string
    {
        $start = $this->startOf($index);

        return self::Quarter === $this
            ? $start->format('Y').'-Q'.(intdiv((int) $start->format('n') - 1, 3) + 1)
            : $start->format('Y-m');
    }
}
