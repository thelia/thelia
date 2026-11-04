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

namespace Thelia\Domain\Reporting\Mailing;

/**
 * The period a report covers, relative to the day it is sent: from the first second of
 * its first day to the last second of its last day, in the timezone of PHP.
 */
enum ReportPeriod: string
{
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case PreviousWeek = 'previous_week';
    case PreviousMonth = 'previous_month';
    case MonthToDate = 'month_to_date';

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     */
    public function resolve(\DateTimeImmutable $reference): array
    {
        $today = $reference->setTime(0, 0);
        $yesterday = $today->modify('-1 day');

        [$start, $end] = match ($this) {
            self::Yesterday => [$yesterday, $yesterday],
            self::Last7Days => [$yesterday->modify('-6 days'), $yesterday],
            self::PreviousWeek => [
                $today->modify('monday this week')->modify('-7 days'),
                $today->modify('monday this week')->modify('-1 day'),
            ],
            self::PreviousMonth => [
                $today->modify('first day of last month'),
                $today->modify('first day of this month')->modify('-1 day'),
            ],
            self::MonthToDate => [$yesterday->modify('first day of this month'), $yesterday],
        };

        return ['start' => $start->setTime(0, 0), 'end' => $end->setTime(23, 59, 59)];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $period): string => $period->value, self::cases());
    }
}
