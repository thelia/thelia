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

namespace Thelia\Tests\Unit\Domain\Subscription;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Subscription\BillingCalendar;
use Thelia\Domain\Subscription\Enum\PeriodUnit;

final class BillingCalendarTest extends TestCase
{
    /**
     * @return iterable<string, array{string, PeriodUnit, int, int, string}>
     */
    public static function periods(): iterable
    {
        yield 'a week is seven days' => ['2028-01-31 10:00:00', PeriodUnit::Week, 1, 31, '2028-02-07 10:00:00'];
        yield 'two weeks' => ['2028-01-31 10:00:00', PeriodUnit::Week, 2, 31, '2028-02-14 10:00:00'];
        yield 'a month on the anchor day' => ['2028-01-15 10:00:00', PeriodUnit::Month, 1, 15, '2028-02-15 10:00:00'];
        yield 'the end of January lands on the 29th of a leap February' => ['2028-01-31 10:00:00', PeriodUnit::Month, 1, 31, '2028-02-29 10:00:00'];
        yield 'the 28th of a common February' => ['2027-01-31 10:00:00', PeriodUnit::Month, 1, 31, '2027-02-28 10:00:00'];
        yield 'back to the anchor day once the month allows it' => ['2028-02-29 10:00:00', PeriodUnit::Month, 1, 31, '2028-03-31 10:00:00'];
        yield 'a 30-day month' => ['2028-03-31 10:00:00', PeriodUnit::Month, 1, 31, '2028-04-30 10:00:00'];
        yield 'a quarter is three months' => ['2028-01-31 10:00:00', PeriodUnit::Quarter, 1, 31, '2028-04-30 10:00:00'];
        yield 'a year' => ['2028-02-29 10:00:00', PeriodUnit::Year, 1, 29, '2029-02-28 10:00:00'];
        yield 'a year back on a leap day' => ['2031-02-28 10:00:00', PeriodUnit::Year, 1, 29, '2032-02-29 10:00:00'];
        yield 'over the end of the year' => ['2028-11-30 10:00:00', PeriodUnit::Month, 2, 30, '2029-01-30 10:00:00'];
    }

    #[DataProvider('periods')]
    public function testAPeriodEndsOnItsAnchorDayOrTheLastDayOfAShorterMonth(string $start, PeriodUnit $unit, int $count, int $anchorDay, string $expected): void
    {
        self::assertSame($expected, (new BillingCalendar())->periodEnd(new \DateTimeImmutable($start), $unit, $count, $anchorDay)->format('Y-m-d H:i:s'));
    }

    public function testThreeMonthlyPeriodsAnchoredOnTheThirtyFirstFollowTheMonths(): void
    {
        $calendar = new BillingCalendar();
        $moment = new \DateTimeImmutable('2028-01-31 08:30:00');
        $ends = [];

        for ($period = 0; $period < 3; ++$period) {
            $moment = $calendar->periodEnd($moment, PeriodUnit::Month, 1, 31);
            $ends[] = $moment->format('Y-m-d');
        }

        self::assertSame(['2028-02-29', '2028-03-31', '2028-04-30'], $ends);
        self::assertSame('2028-04-30', $calendar->advance(new \DateTimeImmutable('2028-01-31 08:30:00'), PeriodUnit::Month, 1, 31, 3)->format('Y-m-d'));
    }

    /**
     * A DATETIME holds whole seconds and the engines disagree on a fraction: MySQL rounds
     * 12:00:00.7 up to 12:00:01, MariaDB cuts it to 12:00:00.
     */
    public function testEveryDateIsCutToTheSecond(): void
    {
        $calendar = new BillingCalendar();
        $start = new \DateTimeImmutable('2028-01-31 12:00:00.700000');

        self::assertSame('000000', $calendar->periodEnd($start, PeriodUnit::Month, 1, 31)->format('u'));
        self::assertSame('2028-02-29 12:00:00.000000', $calendar->periodEnd($start, PeriodUnit::Month, 1, 31)->format('Y-m-d H:i:s.u'));
        self::assertSame('2028-02-07 12:00:00.000000', $calendar->periodEnd($start, PeriodUnit::Week, 1, 31)->format('Y-m-d H:i:s.u'));
        self::assertSame('2028-02-14 12:00:00.000000', $calendar->trialEnd($start, 14)->format('Y-m-d H:i:s.u'));
    }

    public function testATrialEndsItsNumberOfDaysAfterTheStart(): void
    {
        self::assertSame('2028-03-01 10:00:00', (new BillingCalendar())->trialEnd(new \DateTimeImmutable('2028-02-15 10:00:00'), 15)->format('Y-m-d H:i:s'));
    }

    public function testAPeriodOfNoUnitIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BillingCalendar())->periodEnd(new \DateTimeImmutable('2028-01-31'), PeriodUnit::Month, 0, 31);
    }
}
