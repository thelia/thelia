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

namespace Thelia\Tests\Unit\Domain\Company\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\Checkout\Service\SpendingPeriod;

/**
 * Where a calendar period starts, whatever the moment inside it.
 */
final class SpendingPeriodTest extends TestCase
{
    #[DataProvider('moments')]
    public function testAPeriodStartsAtTheFirstSecondOfItsWeekMonthOrYear(string $period, string $now, string $expectedStart): void
    {
        $start = SpendingPeriod::from($period)->startAt(new \DateTimeImmutable($now, new \DateTimeZone('Europe/Paris')));

        self::assertSame($expectedStart, $start->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function moments(): iterable
    {
        // 2026-11-18 is a Wednesday.
        yield 'week from a Wednesday' => ['week', '2026-11-18 15:42:10', '2026-11-16 00:00:00'];
        yield 'week from its Monday' => ['week', '2026-11-16 00:00:00', '2026-11-16 00:00:00'];
        yield 'week from its Sunday, still the Monday before' => ['week', '2026-11-22 23:59:59', '2026-11-16 00:00:00'];
        yield 'week across a month' => ['week', '2026-12-02 09:00:00', '2026-11-30 00:00:00'];
        yield 'week across a year' => ['week', '2027-01-01 09:00:00', '2026-12-28 00:00:00'];
        yield 'month from its middle' => ['month', '2026-11-18 15:42:10', '2026-11-01 00:00:00'];
        yield 'month from its last second' => ['month', '2026-11-30 23:59:59', '2026-11-01 00:00:00'];
        yield 'year from its middle' => ['year', '2026-11-18 15:42:10', '2026-01-01 00:00:00'];
        yield 'year from its first day' => ['year', '2026-01-01 00:00:00', '2026-01-01 00:00:00'];
    }

    public function testTheStartIsReadInTheTimezoneOfTheMoment(): void
    {
        // Sunday night in Paris is already Monday morning in Tokyo: the week of the shop is the one its clock tells.
        $paris = new \DateTimeImmutable('2026-11-22 23:30:00', new \DateTimeZone('Europe/Paris'));
        $tokyo = $paris->setTimezone(new \DateTimeZone('Asia/Tokyo'));

        self::assertSame('2026-11-16 00:00:00', SpendingPeriod::Week->startAt($paris)->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-23 00:00:00', SpendingPeriod::Week->startAt($tokyo)->format('Y-m-d H:i:s'));
    }

    public function testThePeriodsAreTheOnesALimitMayNameAndNoOther(): void
    {
        self::assertSame(['week', 'month', 'year'], array_map(static fn (SpendingPeriod $period): string => $period->value, SpendingPeriod::cases()));
    }
}
