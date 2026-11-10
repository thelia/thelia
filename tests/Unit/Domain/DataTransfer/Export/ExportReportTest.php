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

namespace Thelia\Tests\Unit\Domain\DataTransfer\Export;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Model\ExportJob;

/**
 * A report fits the row of its job whatever the export had to say.
 */
final class ExportReportTest extends TestCase
{
    public function testItKeepsNoMoreWarningsThanItsLimit(): void
    {
        $report = new ExportReport();

        for ($i = 1; $i <= ExportReport::MAX_WARNINGS + 50; ++$i) {
            $report->addWarning('Warning '.$i);
        }

        self::assertCount(ExportReport::MAX_WARNINGS, $report->warnings());
        self::assertSame('Warning 1', $report->warnings()[0]);
        self::assertSame('Warning '.ExportReport::MAX_WARNINGS, $report->warnings()[ExportReport::MAX_WARNINGS - 1]);
    }

    public function testWarningsWithinTheLimitAreLeftAsTheyAre(): void
    {
        $report = new ExportReport();

        for ($i = 1; $i <= ExportReport::MAX_WARNINGS; ++$i) {
            $report->addWarning('Warning '.$i);
        }

        $report->closeWarnings('en_US');

        self::assertCount(ExportReport::MAX_WARNINGS, $report->warnings());
    }

    public function testTheRecordedReportFitsTheColumnWhateverTheWarnings(): void
    {
        $report = new ExportReport();
        $report->addLine('3 pieces.');

        for ($i = 1; $i <= 500; ++$i) {
            $report->addWarning(str_repeat('é', 1000).$i);
        }

        $json = (new ExportJob())->recordReport($report)->getReport();

        self::assertNotNull($json);
        self::assertLessThanOrEqual(ExportJob::REPORT_MAX_BYTES, \strlen($json), 'The column holds bytes, not characters.');
        $stored = json_decode($json, true, 3, \JSON_THROW_ON_ERROR);
        self::assertSame(['3 pieces.'], $stored['lines']);
        self::assertNotSame([], $stored['warnings']);
        self::assertSame(str_repeat('é', 1000).'1', $stored['warnings'][0], 'The warnings are dropped from the end.');
    }

    public function testAReportThatFitsIsRecordedWhole(): void
    {
        $report = new ExportReport();
        $report->addLine('3 pieces.');
        $report->addWarning('One warning.');

        $stored = json_decode((string) (new ExportJob())->recordReport($report)->getReport(), true, 3, \JSON_THROW_ON_ERROR);

        self::assertSame(['lines' => ['3 pieces.'], 'warnings' => ['One warning.']], $stored);
    }
}
