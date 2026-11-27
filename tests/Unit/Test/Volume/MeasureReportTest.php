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

namespace Thelia\Tests\Unit\Test\Volume;

use PHPUnit\Framework\TestCase;
use Thelia\Test\Volume\Measure\MeasureReport;
use Thelia\Test\Volume\Measure\PageMeasure;

final class MeasureReportTest extends TestCase
{
    private const PAGE = ['name' => 'product', 'area' => 'front', 'path' => '/p.html', 'session' => 'anonymous', 'submit' => null];

    public function testThePageCountIsItsFewestQueriesAndACallAboveIsFlagged(): void
    {
        $report = new MeasureReport([]);
        $report->addPage(self::PAGE, [$this->call(143, 0.30), $this->call(140, 0.10), $this->call(140, 0.20)]);

        $page = $report->pages()[0];
        self::assertSame(140, $page['steady']);
        self::assertSame([143, 140, 140], $page['queries']);
        self::assertFalse($page['stable']);
        self::assertSame(0.2, $page['firstByteMedian']);
    }

    public function testCallsWithTheSameCountAreStable(): void
    {
        $report = new MeasureReport([]);
        $report->addPage(self::PAGE, [$this->call(90, 0.1), $this->call(90, 0.1)]);

        self::assertTrue($report->pages()[0]['stable']);
    }

    public function testACampaignWithTheSameCountsHasNoDifference(): void
    {
        $report = new MeasureReport([]);
        $report->addPage(self::PAGE, [$this->call(140, 0.1)]);

        self::assertSame([], $report->differencesWith(['pages' => [['name' => 'product', 'steady' => 140]]]));
    }

    public function testACountThatChangedOrAPageMeasuredOnceIsADifference(): void
    {
        $report = new MeasureReport([]);
        $report->addPage(self::PAGE, [$this->call(141, 0.1)]);
        $report->addPage(['name' => 'search'] + self::PAGE, [$this->call(40, 0.1)]);

        self::assertSame(
            ['product: 140 queries before, 141 now', 'search: not measured by the previous campaign', 'home: not measured by this campaign'],
            $report->differencesWith(['pages' => [['name' => 'product', 'steady' => 140], ['name' => 'home', 'steady' => 90]]]),
        );
    }

    public function testTheMedianOfAnEvenNumberOfValuesIsTheMeanOfTheTwoMiddleOnes(): void
    {
        self::assertSame(2.5, MeasureReport::median([4.0, 1.0, 2.0, 3.0]));
        self::assertSame(2.0, MeasureReport::median([3.0, 1.0, 2.0]));
    }

    private function call(int $queries, float $firstByte): PageMeasure
    {
        return new PageMeasure(200, $queries, $firstByte, $firstByte, 1000);
    }
}
