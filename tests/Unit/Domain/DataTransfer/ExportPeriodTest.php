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

namespace Thelia\Tests\Unit\Domain\DataTransfer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\Export\ExportPeriod;

final class ExportPeriodTest extends TestCase
{
    public function testAYearAndAMonthCoverTheWholeMonth(): void
    {
        $period = ExportPeriod::resolve(['start' => ['year' => '2024', 'month' => '2'], 'end' => ['year' => '2024', 'month' => '2']]);

        self::assertSame('2024-02-01 00:00:00', $period['start']?->format('Y-m-d H:i:s'));
        self::assertSame('2024-02-29 23:59:59', $period['end']?->format('Y-m-d H:i:s'));
    }

    public function testADateIsKeptAsItIs(): void
    {
        $date = new \DateTimeImmutable('2024-03-15 10:00:00');

        self::assertSame($date, ExportPeriod::resolve(['start' => $date])['start']);
    }

    public function testABoundLeftEmptyIsNoBound(): void
    {
        self::assertSame(['start' => null, 'end' => null], ExportPeriod::resolve(['start' => '', 'end' => null]));
        self::assertNull(ExportPeriod::resolve(null));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function boundsTheFormCannotHaveSent(): iterable
    {
        yield 'text after the year' => [['year' => '2026; DROP', 'month' => '1']];
        yield 'a year of five digits' => [['year' => '99999', 'month' => '1']];
        yield 'a year of two digits' => [['year' => '26', 'month' => '1']];
        yield 'a thirteenth month' => [['year' => '2026', 'month' => '13']];
        yield 'a month zero' => [['year' => '2026', 'month' => '0']];
    }

    /**
     * Anything but a year of four digits and a month of the year is refused, rather
     * than read as another date or as no bound at all.
     *
     * @param array<string, string> $bound
     */
    #[DataProvider('boundsTheFormCannotHaveSent')]
    public function testABoundTheFormCannotHaveSentIsRefused(array $bound): void
    {
        $this->expectException(JobRefusedException::class);
        $this->expectExceptionMessage(ExportPeriod::INVALID_DATES);

        ExportPeriod::resolve(['start' => $bound, 'end' => null]);
    }
}
