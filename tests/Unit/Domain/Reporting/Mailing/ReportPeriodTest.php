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

namespace Thelia\Tests\Unit\Domain\Reporting\Mailing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Reporting\Mailing\ReportPeriod;

final class ReportPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{ReportPeriod, string, string, string}>
     */
    public static function periods(): iterable
    {
        // Wednesday 4 November 2026.
        yield 'yesterday' => [ReportPeriod::Yesterday, '2026-11-04 15:30:00', '2026-11-03 00:00:00', '2026-11-03 23:59:59'];
        yield 'last 7 days end yesterday' => [ReportPeriod::Last7Days, '2026-11-04 15:30:00', '2026-10-28 00:00:00', '2026-11-03 23:59:59'];
        yield 'previous week on a Wednesday' => [ReportPeriod::PreviousWeek, '2026-11-04 15:30:00', '2026-10-26 00:00:00', '2026-11-01 23:59:59'];
        yield 'previous week on a Monday' => [ReportPeriod::PreviousWeek, '2026-11-02 07:00:00', '2026-10-26 00:00:00', '2026-11-01 23:59:59'];
        yield 'previous week on a Sunday' => [ReportPeriod::PreviousWeek, '2026-11-08 23:00:00', '2026-10-26 00:00:00', '2026-11-01 23:59:59'];
        yield 'previous month' => [ReportPeriod::PreviousMonth, '2026-11-04 15:30:00', '2026-10-01 00:00:00', '2026-10-31 23:59:59'];
        yield 'previous month from the 31st' => [ReportPeriod::PreviousMonth, '2026-03-31 08:00:00', '2026-02-01 00:00:00', '2026-02-28 23:59:59'];
        yield 'previous month across the year' => [ReportPeriod::PreviousMonth, '2027-01-15 08:00:00', '2026-12-01 00:00:00', '2026-12-31 23:59:59'];
        yield 'month to date' => [ReportPeriod::MonthToDate, '2026-11-04 15:30:00', '2026-11-01 00:00:00', '2026-11-03 23:59:59'];
        yield 'month to date on the 1st is the whole previous month' => [ReportPeriod::MonthToDate, '2026-11-01 07:00:00', '2026-10-01 00:00:00', '2026-10-31 23:59:59'];
        yield 'yesterday across a leap day' => [ReportPeriod::Yesterday, '2028-03-01 00:10:00', '2028-02-29 00:00:00', '2028-02-29 23:59:59'];
    }

    #[DataProvider('periods')]
    public function testThePeriodIsResolvedAroundTheReference(ReportPeriod $period, string $reference, string $start, string $end): void
    {
        $resolved = $period->resolve(new \DateTimeImmutable($reference));

        self::assertSame($start, $resolved['start']->format('Y-m-d H:i:s'));
        self::assertSame($end, $resolved['end']->format('Y-m-d H:i:s'));
    }

    public function testTheValuesAreTheOnesStoredInTheTable(): void
    {
        self::assertSame(['yesterday', 'last_7_days', 'previous_week', 'previous_month', 'month_to_date'], ReportPeriod::values());
    }
}
