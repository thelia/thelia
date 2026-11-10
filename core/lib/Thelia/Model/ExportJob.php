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

namespace Thelia\Model;

use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Domain\DataTransfer\Job\DataTransferJob;
use Thelia\Model\Base\ExportJob as BaseExportJob;
use Thelia\Model\Tools\DataTransferJobTrait;

class ExportJob extends BaseExportJob implements DataTransferJob
{
    use DataTransferJobTrait;

    /**
     * The size of the column of the report, in bytes.
     */
    public const REPORT_MAX_BYTES = 65535;

    /**
     * The report as the column can hold it: warnings are dropped from the end until it fits,
     * so that no report keeps the job from being saved.
     */
    public function recordReport(ExportReport $report): static
    {
        $lines = $report->lines();
        $warnings = $report->warnings();

        do {
            $json = json_encode(['lines' => $lines, 'warnings' => $warnings], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } while (\strlen($json) > self::REPORT_MAX_BYTES && (null !== array_pop($warnings) || null !== array_pop($lines)));

        return $this->setReport($json);
    }

    /**
     * What the export reported, NULL for an export that reports nothing. A report that
     * cannot be read is shown empty rather than failing the screen of the job.
     */
    public function getExportReport(): ?ExportReport
    {
        if (null === $this->getReport()) {
            return null;
        }

        try {
            $stored = json_decode($this->getReport(), true, 3, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new ExportReport();
        }

        $strings = static fn (mixed $values): array => \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];

        return ExportReport::recorded($strings($stored['lines'] ?? null), $strings($stored['warnings'] ?? null));
    }
}
