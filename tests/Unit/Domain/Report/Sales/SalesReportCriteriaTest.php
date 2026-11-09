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

namespace Thelia\Tests\Unit\Domain\Report\Sales;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;

final class SalesReportCriteriaTest extends TestCase
{
    public function testThePreviousPeriodHasTheSameLengthAndEndsTheSecondBeforeThisOneStarts(): void
    {
        $june = new SalesReportCriteria(
            new \DateTimeImmutable('2026-06-01 00:00:00'),
            new \DateTimeImmutable('2026-06-30 23:59:59'),
            StatusScope::All,
            [3, 7],
        );

        $previous = $june->previous();

        self::assertSame('2026-05-02 00:00:00', $previous->from->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-31 23:59:59', $previous->to->format('Y-m-d H:i:s'));
        self::assertSame(StatusScope::All, $previous->scope);
        self::assertSame([3, 7], $previous->saleUnitIds);
    }

    /**
     * Days of the shop's calendar, not seconds: a period over the change to summer time is an
     * hour short, and its previous period would otherwise start at 1 am.
     */
    #[DataProvider('periodsOverAClockChange')]
    public function testThePreviousPeriodOfWholeDaysStartsAtMidnightAcrossAClockChange(string $from, string $to, string $previousFrom, string $previousTo): void
    {
        $paris = new \DateTimeZone('Europe/Paris');

        $previous = (new SalesReportCriteria(new \DateTimeImmutable($from, $paris), new \DateTimeImmutable($to, $paris)))->previous();

        self::assertSame([$previousFrom, $previousTo], [$previous->from->format('Y-m-d H:i:s'), $previous->to->format('Y-m-d H:i:s')]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function periodsOverAClockChange(): iterable
    {
        yield 'this period over the change to summer time' => ['2026-03-20 00:00:00', '2026-04-08 23:59:59', '2026-02-28 00:00:00', '2026-03-19 23:59:59'];
        yield 'the previous period over the change to winter time' => ['2026-11-01 00:00:00', '2026-11-09 23:59:59', '2026-10-23 00:00:00', '2026-10-31 23:59:59'];
    }

    public function testThePreviousPeriodOfASingleSecondIsTheSecondBefore(): void
    {
        $moment = new \DateTimeImmutable('2026-01-01 00:00:00');

        $previous = (new SalesReportCriteria($moment, $moment))->previous();

        self::assertSame(['2025-12-31 23:59:59', '2025-12-31 23:59:59'], [$previous->from->format('Y-m-d H:i:s'), $previous->to->format('Y-m-d H:i:s')]);
        self::assertSame(StatusScope::Paid, $previous->scope);
        self::assertNull($previous->saleUnitIds);
    }

    public function testAPeriodCannotEndBeforeItStarts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SalesReportCriteria(new \DateTimeImmutable('2026-06-02'), new \DateTimeImmutable('2026-06-01'));
    }
}
