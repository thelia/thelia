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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryEstimate;

/**
 * The arithmetic of a delivery estimate, on calendars it is handed: no query, no clock.
 *
 * The day the order arrives is the first day of its preparation, unless the shop is closed
 * that day or the order comes in at or after the cutoff hour: preparation then starts on
 * the next working day of the shop. The parcel leaves at the end of the last day of
 * preparation, and travels the given number of working days of the carrier. So one day of
 * preparation and two of transit deliver an order of Tuesday morning on Thursday.
 *
 * A preparation of zero days ships the day the order is taken, like a preparation of one:
 * both mean the parcel leaves the day it comes in. A transit of zero days delivers on the
 * day the parcel leaves, or on the carrier's next working day when it does not deliver
 * that day.
 */
final readonly class DeliveryIntervalCalculator
{
    /**
     * How far a calendar is walked before giving up: a shop or a carrier closed on every day
     * of the week would otherwise never reach a working day.
     */
    public const int SEARCH_LIMIT_DAYS = 366;

    /**
     * The most working days a count goes to: a year of preparation and a year of the goods.
     * Beyond, a delay is read as this one.
     */
    public const int MAXIMUM_WORKING_DAYS = 2 * self::SEARCH_LIMIT_DAYS;

    /**
     * Closures are runs of days, both bounds included, as Y-m-d: a closure of years is one run
     * to compare with, not years of days.
     *
     * @param list<int>                   $shopClosedWeekdays    ISO numbers, 1 (Monday) to 7 (Sunday)
     * @param list<array{string, string}> $shopClosedDays        runs of days the shop is closed
     * @param list<int>                   $carrierClosedWeekdays ISO numbers the carrier does not deliver on
     * @param list<array{string, string}> $carrierClosedDays     runs of days the carrier does not deliver
     * @param string|null                 $cutoff                the local hour, H:i, from which an order is prepared the next day
     */
    public function interval(
        \DateTimeImmutable $now,
        int $preparationDays,
        int $transitMinDays,
        int $transitMaxDays,
        array $shopClosedWeekdays,
        array $shopClosedDays,
        array $carrierClosedWeekdays,
        array $carrierClosedDays,
        ?string $cutoff = null,
    ): ?DeliveryEstimate {
        // A week closed every day has no working day to find: nothing to walk.
        if (self::closedAllWeek($shopClosedWeekdays) || self::closedAllWeek($carrierClosedWeekdays)) {
            return null;
        }

        $start = $now->setTime(0, 0);

        if (null !== $cutoff && $now->format('H:i') >= $cutoff) {
            $start = $start->modify('+1 day');
        }

        $shopOpen = static fn (\DateTimeImmutable $day): bool => self::isOpen($day, $shopClosedWeekdays, $shopClosedDays);
        $carrierOpen = static fn (\DateTimeImmutable $day): bool => self::isOpen($day, $carrierClosedWeekdays, $carrierClosedDays);

        $shipped = self::nthOpenDay($start, max(1, $preparationDays), $shopOpen);

        if (null === $shipped) {
            return null;
        }

        $earliest = self::afterOpenDays($shipped, max(0, $transitMinDays), $carrierOpen);
        $latest = self::afterOpenDays($shipped, max($transitMinDays, $transitMaxDays, 0), $carrierOpen);

        if (null === $earliest || null === $latest) {
            return null;
        }

        return new DeliveryEstimate($earliest, $latest);
    }

    /**
     * @param list<int> $closedWeekdays
     */
    private static function closedAllWeek(array $closedWeekdays): bool
    {
        return 7 === \count(array_intersect([1, 2, 3, 4, 5, 6, 7], $closedWeekdays));
    }

    /**
     * @param list<int>                   $closedWeekdays
     * @param list<array{string, string}> $closedRuns
     */
    private static function isOpen(\DateTimeImmutable $day, array $closedWeekdays, array $closedRuns): bool
    {
        if (\in_array((int) $day->format('N'), $closedWeekdays, true)) {
            return false;
        }

        $key = $day->format('Y-m-d');

        foreach ($closedRuns as [$first, $last]) {
            // Y-m-d days compare as strings.
            if ($first <= $key && $key <= $last) {
                return false;
            }
        }

        return true;
    }

    /**
     * The nth open day counting from $from itself.
     *
     * @param callable(\DateTimeImmutable): bool $open
     */
    private static function nthOpenDay(\DateTimeImmutable $from, int $nth, callable $open): ?\DateTimeImmutable
    {
        $nth = min($nth, self::MAXIMUM_WORKING_DAYS);
        $counted = 0;

        for ($walked = 0, $day = $from; $walked <= 7 * $nth + self::SEARCH_LIMIT_DAYS; ++$walked, $day = $day->modify('+1 day')) {
            if ($open($day) && ++$counted === $nth) {
                return $day;
            }
        }

        return null;
    }

    /**
     * The day reached after $days open days following $from; with none, the first open day
     * from $from itself.
     *
     * @param callable(\DateTimeImmutable): bool $open
     */
    private static function afterOpenDays(\DateTimeImmutable $from, int $days, callable $open): ?\DateTimeImmutable
    {
        return 0 === $days
            ? self::nthOpenDay($from, 1, $open)
            : self::nthOpenDay($from->modify('+1 day'), $days, $open);
    }
}
