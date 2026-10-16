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
            $this->interval(self::TUESDAY_MORNING, 1, 2, 2, carrierClosedDays: ['2026-09-03' => true]),
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

    public function testADelayOfMillionsOfDaysIsNotWalkedDayByDay(): void
    {
        $started = microtime(true);

        self::assertNull($this->interval(self::TUESDAY_MORNING, 1000000000, 0, 0));
        self::assertLessThan(1.0, microtime(true) - $started);
    }

    public function testACalendarWithoutAnyWorkingDayGivesNoEstimate(): void
    {
        self::assertNull($this->interval(self::TUESDAY_MORNING, 1, 1, 1, shopClosedWeekdays: [1, 2, 3, 4, 5, 6, 7]));
        self::assertNull($this->interval(self::TUESDAY_MORNING, 1, 1, 1, carrierClosedWeekdays: [1, 2, 3, 4, 5, 6, 7]));
    }

    /**
     * @param list<int>           $shopClosedWeekdays
     * @param array<string, true> $carrierClosedDays
     * @param list<int>           $carrierClosedWeekdays
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
