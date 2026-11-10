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

namespace Thelia\Tests\Support\DataTransfer;

use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Domain\DataTransfer\Export\ReportingExportInterface;

/**
 * An export of two rows with a report of many long warnings.
 */
final class VerboseReportExport extends ArrayAbstractExport implements ReportingExportInterface
{
    public static int $warnings = 300;

    public static int $warningLength = 1000;

    public static string $fileName = 'verbose-report';

    private ExportReport $report;

    public function __construct()
    {
        $this->report = new ExportReport();
    }

    public function report(): ExportReport
    {
        return $this->report;
    }

    public function getFileName(): string
    {
        return self::$fileName;
    }

    protected function getData(): array
    {
        $this->report->addLine('2 rows.');

        for ($i = 1; $i <= self::$warnings; ++$i) {
            $this->report->addWarning($i.' '.str_repeat('é', self::$warningLength));
        }

        $this->report->closeWarnings('en_US');

        return [['id' => 1], ['id' => 2]];
    }
}
