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
use Thelia\Domain\Reporting\Mailing\DueReport;
use Thelia\Domain\Reporting\Mailing\ReportDeliveryMode;
use Thelia\Domain\Reporting\Mailing\ReportDueResolver;
use Thelia\Domain\Reporting\Mailing\ReportFrequency;
use Thelia\Domain\Reporting\Mailing\ReportMailingSettings;
use Thelia\Domain\Reporting\Mailing\ReportPeriod;
use Thelia\Domain\Reporting\Mailing\ReportSchedule;

final class ReportDueResolverTest extends TestCase
{
    public function testADailyReportIsDueFromItsHourAndOnlyOncePerPeriod(): void
    {
        $schedule = $this->daily(sendHour: 7);

        self::assertSame('2026-11-03', $this->dueNow($schedule, '2026-11-04 07:00:00')->periodEnd->format('Y-m-d'));
        self::assertSame('2026-11-02', $this->dueNow($schedule, '2026-11-04 06:59:59')->periodEnd->format('Y-m-d'), 'before its hour, the occurrence is yesterday');
    }

    public function testADailyReportAlreadySentIsNotDueAgain(): void
    {
        self::assertNull($this->due($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-03'), '2026-11-04 12:00:00'));
        self::assertNotNull($this->due($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-02'), '2026-11-04 12:00:00'));
    }

    public function testAWeeklyReportIsDueFromItsDayAndHour(): void
    {
        // Monday 07:00, the week before.
        $schedule = new ReportSchedule(ReportFrequency::Weekly, ReportPeriod::PreviousWeek, 7, 1, null, null);

        $onTheDay = $this->dueNow($schedule, '2026-11-02 07:00:00');
        self::assertSame('2026-11-02 07:00:00', $onTheDay->occurrence->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-26', $onTheDay->periodStart->format('Y-m-d'));
        self::assertSame('2026-11-01', $onTheDay->periodEnd->format('Y-m-d'));

        $beforeTheHour = $this->dueNow($schedule, '2026-11-02 06:59:00');
        self::assertSame('2026-10-26 07:00:00', $beforeTheHour->occurrence->format('Y-m-d H:i:s'), 'one week earlier');

        $midWeek = $this->dueNow($schedule, '2026-11-05 12:00:00');
        self::assertSame('2026-11-02 07:00:00', $midWeek->occurrence->format('Y-m-d H:i:s'));
    }

    public function testAWeeklyReportSentForTheWeekIsNotDueBeforeNextWeek(): void
    {
        $schedule = new ReportSchedule(ReportFrequency::Weekly, ReportPeriod::PreviousWeek, 7, 1, null, new \DateTimeImmutable('2026-11-01'));

        self::assertNull($this->due($schedule, '2026-11-05 12:00:00'));
        self::assertSame('2026-11-08', $this->dueNow($schedule, '2026-11-09 07:00:00')->periodEnd->format('Y-m-d'));
    }

    public function testAWeeklyReportOnASundayIsDueOnThatSunday(): void
    {
        $schedule = new ReportSchedule(ReportFrequency::Weekly, ReportPeriod::Last7Days, 18, 7, null, null);

        self::assertSame('2026-11-08 18:00:00', $this->dueNow($schedule, '2026-11-08 18:00:00')->occurrence->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-01 18:00:00', $this->dueNow($schedule, '2026-11-08 17:59:59')->occurrence->format('Y-m-d H:i:s'));
    }

    public function testAMonthlyReportIsDueFromItsDayOfTheMonth(): void
    {
        $schedule = new ReportSchedule(ReportFrequency::Monthly, ReportPeriod::PreviousMonth, 6, null, 28, null);

        self::assertSame('2026-10-28 06:00:00', $this->dueNow($schedule, '2026-11-04 12:00:00')->occurrence->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-28 06:00:00', $this->dueNow($schedule, '2026-11-28 06:00:00')->occurrence->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-28 06:00:00', $this->dueNow($schedule, '2026-12-30 06:00:00')->occurrence->format('Y-m-d H:i:s'));
    }

    public function testAMonthlyReportBeforeItsDayAcrossTheYearTakesTheLastMonth(): void
    {
        $schedule = new ReportSchedule(ReportFrequency::Monthly, ReportPeriod::PreviousMonth, 6, null, 15, null);

        $due = $this->dueNow($schedule, '2027-01-03 06:00:00');

        self::assertSame('2026-12-15 06:00:00', $due->occurrence->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-30', $due->periodEnd->format('Y-m-d'));
    }

    public function testAMonthlyReportSentForThePeriodIsNotDueAgain(): void
    {
        $schedule = new ReportSchedule(ReportFrequency::Monthly, ReportPeriod::PreviousMonth, 6, null, 1, new \DateTimeImmutable('2026-10-31'));

        self::assertNull($this->due($schedule, '2026-11-20 12:00:00'));
        self::assertNotNull($this->due($schedule, '2026-12-01 06:00:00'));
    }

    public function testRecipientsStillWaitingGetThePeriodAgainOnTheirOwn(): void
    {
        $schedule = $this->daily(sendHour: 7, lastPeriodEnd: '2026-11-03', pending: ['late@example.com']);

        $due = $this->dueNow($schedule, '2026-11-04 12:00:00');

        self::assertSame(['late@example.com'], $due->onlyRecipients);
        self::assertSame('2026-11-03', $due->periodEnd->format('Y-m-d'));
    }

    public function testANewPeriodGoesToEveryoneWhateverIsPending(): void
    {
        $schedule = $this->daily(sendHour: 7, lastPeriodEnd: '2026-11-02', pending: ['late@example.com']);

        $due = $this->dueNow($schedule, '2026-11-04 12:00:00');

        self::assertNull($due->onlyRecipients);
        self::assertSame('2026-11-03', $due->periodEnd->format('Y-m-d'));
    }

    public function testARetryCountsItsTriesAndTheThirdOneIsTheLast(): void
    {
        $first = $this->dueNow($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-02'), '2026-11-04 12:00:00');
        self::assertSame(1, $first->attempt, 'the first send is the first try');
        self::assertFalse($first->lastAttempt);

        $secondTry = $this->dueNow($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-03', pending: ['late@example.com']), '2026-11-04 12:00:00');
        self::assertSame(2, $secondTry->attempt);
        self::assertFalse($secondTry->lastAttempt);

        $thirdTry = $this->dueNow($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-03', pending: ['late@example.com'], pendingAttempts: 1), '2026-11-04 12:00:00');
        self::assertSame(3, $thirdTry->attempt);
        self::assertTrue($thirdTry->lastAttempt, 'the recipients still failing after it are given up');
    }

    public function testANewPeriodStartsTheCountOverWhateverWasTried(): void
    {
        $due = $this->dueNow($this->daily(sendHour: 7, lastPeriodEnd: '2026-11-02', pending: ['late@example.com'], pendingAttempts: 1), '2026-11-04 12:00:00');

        self::assertSame(1, $due->attempt);
        self::assertFalse($due->lastAttempt);
    }

    /**
     * @return iterable<string, array{0: int, 1: ?int, 2: ?int}>
     */
    public static function outOfRangeSchedules(): iterable
    {
        yield 'hour 24' => [24, null, null];
        yield 'hour -1' => [-1, null, null];
        yield 'weekday 0' => [7, 0, null];
        yield 'weekday 8' => [7, 8, null];
        yield 'month day 0' => [7, null, 0];
        yield 'month day 29' => [7, null, 29];
        yield 'month day 31' => [7, null, 31];
    }

    #[DataProvider('outOfRangeSchedules')]
    public function testAScheduleOutsideItsBoundsIsRefusedWhenBuilt(int $sendHour, ?int $dayOfWeek, ?int $dayOfMonth): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReportSchedule(ReportFrequency::Daily, ReportPeriod::Yesterday, $sendHour, $dayOfWeek, $dayOfMonth, null);
    }

    public function testTheBoundsThemselvesAreAccepted(): void
    {
        foreach ([[0, 1, 1], [23, 7, 28]] as [$hour, $weekday, $monthDay]) {
            $schedule = new ReportSchedule(ReportFrequency::Weekly, ReportPeriod::Yesterday, $hour, $weekday, $monthDay, null);

            self::assertSame($hour, $schedule->sendHour);
        }
    }

    public function testTheAttachmentLimitDecidesBetweenTheFileAndTheLink(): void
    {
        $fiveMegabytes = 5 * 1024 * 1024;

        self::assertSame(ReportDeliveryMode::Attached, ReportMailingSettings::chooseDelivery(1024, $fiveMegabytes));
        self::assertSame(ReportDeliveryMode::Attached, ReportMailingSettings::chooseDelivery($fiveMegabytes, $fiveMegabytes), 'the limit itself still fits');
        self::assertSame(ReportDeliveryMode::Linked, ReportMailingSettings::chooseDelivery($fiveMegabytes + 1, $fiveMegabytes));
        self::assertSame(ReportDeliveryMode::Linked, ReportMailingSettings::chooseDelivery(1, 0), 'a limit of 0 means always a link');
        self::assertSame(ReportDeliveryMode::Linked, ReportMailingSettings::chooseDelivery(0, 0));
    }

    /**
     * @param list<string> $pending
     */
    private function daily(int $sendHour, ?string $lastPeriodEnd = null, array $pending = [], int $pendingAttempts = 0): ReportSchedule
    {
        return new ReportSchedule(ReportFrequency::Daily, ReportPeriod::Yesterday, $sendHour, null, null, null === $lastPeriodEnd ? null : new \DateTimeImmutable($lastPeriodEnd), $pending, $pendingAttempts);
    }

    private function due(ReportSchedule $schedule, string $now): ?DueReport
    {
        return (new ReportDueResolver())->resolve($schedule, new \DateTimeImmutable($now));
    }

    private function dueNow(ReportSchedule $schedule, string $now): DueReport
    {
        return $this->due($schedule, $now) ?? self::fail('The report should be due.');
    }
}
