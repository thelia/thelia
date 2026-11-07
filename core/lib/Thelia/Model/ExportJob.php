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

    public function recordReport(ExportReport $report): static
    {
        return $this->setReport(json_encode(['lines' => $report->lines(), 'warnings' => $report->warnings()], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
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

        $report = new ExportReport();

        try {
            $stored = json_decode($this->getReport(), true, 3, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $report;
        }

        foreach (\is_array($stored['lines'] ?? null) ? $stored['lines'] : [] as $line) {
            if (\is_string($line)) {
                $report->addLine($line);
            }
        }

        foreach (\is_array($stored['warnings'] ?? null) ? $stored['warnings'] : [] as $warning) {
            if (\is_string($warning)) {
                $report->addWarning($warning);
            }
        }

        return $report;
    }
}
