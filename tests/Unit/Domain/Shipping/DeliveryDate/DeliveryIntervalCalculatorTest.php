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

namespace Thelia\Tests\Unit\Domain\Shipping\DeliveryDate;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryIntervalCalculator;

final class DeliveryIntervalCalculatorTest extends TestCase
{
    private const array WEEKEND = [6, 7];

    /** 2026-09-01 is a Tuesday. */
    private const string TUESDAY_MORNING = '2026-09-01 09:30';

    public function testAnOrderOfTuesdayMorningPreparedOneDayAndCarriedTwoArrivesOnThursday(): void
    {
        self::assertSame(['2026-09-03', '2026-09-03'], $this->interval(self::TUESDAY_MORNING, 1, 2, 2));
    }

    public function testTheIntervalSpansTheFewestAndTheMostTransitDays(): void
    {
        self::assertSame(['2026-09-03', '2026-09-07'], $this->interval(self::TUESDAY_MORNING, 1, 2, 4));
    }

    public function testAnOrderAtOrAfterTheCutoffIsPreparedTheNextDay(): void
    {
        self::assertSame(['2026-09-04', '2026-09-04'], $this->interval('2026-09-01 14:00', 1, 2, 2, cutoff: '14:00'));
        self::assertSame(['2026-09-03', '2026-09-03'], $this->interval('2026-09-01 13:59', 1, 2, 2, cutoff: '14:00'));
    }

    public function testAClosedDayOfTheShopDoesNotCountAsPreparation(): void
    {
        // Friday order, two days of preparation: Friday and Monday, the weekend skipped.
        self::assertSame(['2026-09-08', '2026-09-08'], $this->interval('2026-09-04 10:00', 2, 1, 1));
    }

    public function testAnOrderOnADayTheShopIsClosedStartsOnTheNextWorkingDay(): void
    {
        self::assertSame(['2026-09-08', '2026-09-08'], $this->interval('2026-09-05 10:00', 1, 1, 1));
    }

    public function testAHolidayInTheIntervalDoesNotCount(): void
    {
        // Thursday 3 is a holiday of the carrier: the parcel lands on Friday.
        self::assertSame(
            ['2026-09-04', '2026-09-04'],
            $this->interval(self::TUESDAY_MORNING, 1, 2, 2, carrierClosedDays: [['2026-09-03', '2026-09-03']]),
        );
    }

    public function testAProductSlowerThanTheCarrierPreparationStretchesThePreparation(): void
    {
        // One day of the carrier plus five of the product: the sixth working day from Tuesday 1.
        self::assertSame(['2026-09-08', '2026-09-08'], $this->interval(self::TUESDAY_MORNING, 1 + 5, 0, 0));
    }

    public function testZeroDaysOfPreparationShipTheDayTheOrderComesIn(): void
    {
        self::assertSame(['2026-09-02', '2026-09-02'], $this->interval(self::TUESDAY_MORNING, 0, 1, 1));
    }

    public function testZeroDaysOfTransitDeliverOnTheCarriersNextWorkingDay(): void
    {
        // Shipped on Saturday by a shop open every day, delivered by a carrier closed on Sundays.
        self::assertSame(
            ['2026-09-05', '2026-09-07'],
            $this->interval('2026-09-05 10:00', 1, 0, 1, shopClosedWeekdays: [], carrierClosedWeekdays: [7]),
        );
        self::assertSame(
            ['2026-09-07', '2026-09-07'],
            $this->interval('2026-09-06 10:00', 1, 0, 0, shopClosedWeekdays: [], carrierClosedWeekdays: [7]),
        );
    }

    public function testTheIntervalCrossesTheYear(): void
    {
        self::assertSame(['2027-01-04', '2027-01-05'], $this->interval('2026-12-31 10:00', 1, 2, 3));
    }

    public function testADaylightSavingChangeDoesNotShiftTheDay(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            // The clocks go back on Sunday 25 October 2026.
            self::assertSame(['2026-10-26', '2026-10-27'], $this->interval('2026-10-23 23:30', 0, 1, 2));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testADelayOfMillionsOfDaysIsCountedAsTheLongestCount(): void
    {
        $started = microtime(true);

        $millions = $this->interval(self::TUESDAY_MORNING, 1000000000, 0, 0);

        self::assertLessThan(1.0, microtime(true) - $started, 'The delay was walked day by day.');
        self::assertSame($this->interval(self::TUESDAY_MORNING, DeliveryIntervalCalculator::MAXIMUM_WORKING_DAYS, 0, 0), $millions);
        self::assertNotNull($millions);
    }

    public function testACalendarOpenOneDayAWeekStillReachesTheLongestCount(): void
    {
        // Open on Mondays only: 732 working days are about fourteen years away.
        self::assertNotNull($this->interval(self::TUESDAY_MORNING, DeliveryIntervalCalculator::MAXIMUM_WORKING_DAYS, 0, 0, shopClosedWeekdays: [2, 3, 4, 5, 6, 7], carrierClosedWeekdays: [2, 3, 4, 5, 6, 7]));
    }

    public function testAClosureOfDecadesIsOneRun(): void
    {
        // Closed from Wednesday 2 to Friday 4 September, then for seventy years from 2030.
        $closed = [['2026-09-02', '2026-09-04'], ['2030-01-01', '2099-12-31']];

        $started = microtime(true);
        $interval = $this->interval(self::TUESDAY_MORNING, 1, 1, 1, carrierClosedDays: $closed);

        self::assertLessThan(0.1, microtime(true) - $started);
        self::assertSame(['2026-09-07', '2026-09-07'], $interval);
    }

    public function testAWeekClosedEveryDayIsNotWalked(): void
    {
        // A product page estimates every carrier at every visit: a thousand of them must not walk.
        $started = microtime(true);

        for ($visit = 0; $visit < 1000; ++$visit) {
            self::assertNull($this->interval(self::TUESDAY_MORNING, DeliveryIntervalCalculator::MAXIMUM_WORKING_DAYS, 366, 366, carrierClosedWeekdays: [1, 2, 3, 4, 5, 6, 7]));
        }

        self::assertLessThan(0.2, microtime(true) - $started, 'A week closed every day was walked.');
    }

    public function testAClosureIsSteppedOverInTheTimeZoneOfTheCount(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            // Counted in New York while PHP runs in UTC: Thursday 3 stays closed.
            $estimate = (new DeliveryIntervalCalculator())->interval(
                new \DateTimeImmutable('2026-09-01 09:30', new \DateTimeZone('America/New_York')),
                1, 2, 2, [6, 7], [], [6, 7], [['2026-09-03', '2026-09-03']],
            );
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertNotNull($estimate);
        self::assertSame('2026-09-04', $estimate->earliestDay());
        self::assertSame('America/New_York', $estimate->earliest->getTimezone()->getName(), 'The count left its time zone.');
    }

    public function testAClosureIsSteppedOverInOneStep(): void
    {
        $firstOpenFrom = new \ReflectionMethod(DeliveryIntervalCalculator::class, 'firstOpenFrom');

        $next = $firstOpenFrom->invoke(null, new \DateTimeImmutable('2026-09-02'), [], [['2026-09-02', '9999-12-31']]);

        self::assertSame('10000-01-01', $next->format('Y-m-d'));
    }

    public function testACalendarWithoutAnyWorkingDayGivesNoEstimate(): void
    {
        self::assertNull($this->interval(self::TUESDAY_MORNING, 1, 1, 1, shopClosedWeekdays: [1, 2, 3, 4, 5, 6, 7]));
        self::assertNull($this->interval(self::TUESDAY_MORNING, 1, 1, 1, carrierClosedWeekdays: [1, 2, 3, 4, 5, 6, 7]));
    }

    /**
     * @param list<int>                   $shopClosedWeekdays
     * @param list<array{string, string}> $carrierClosedDays
     * @param list<int>                   $carrierClosedWeekdays
     *
     * @return array{string, string}|null
     */
    private function interval(
        string $now,
        int $preparation,
        int $transitMin,
        int $transitMax,
        ?string $cutoff = null,
        array $shopClosedWeekdays = self::WEEKEND,
        array $carrierClosedDays = [],
        array $carrierClosedWeekdays = self::WEEKEND,
    ): ?array {
        $estimate = (new DeliveryIntervalCalculator())->interval(
            new \DateTimeImmutable($now),
            $preparation,
            $transitMin,
            $transitMax,
            $shopClosedWeekdays,
            [],
            $carrierClosedWeekdays,
            $carrierClosedDays,
            $cutoff,
        );

        return null === $estimate ? null : [$estimate->earliestDay(), $estimate->latestDay()];
    }
}
