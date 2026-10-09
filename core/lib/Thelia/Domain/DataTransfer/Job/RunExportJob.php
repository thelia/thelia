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

namespace Thelia\Domain\DataTransfer\Job;

use Thelia\Messenger\Message\DescribedJob;
use Thelia\Messenger\Message\ReplayableJob;

/**
 * Runs the export described by one export_job row.
 *
 * Only the id travels: what to export and how is read from the row when the job
 * runs, so a job replayed from the failure transport runs on the row as it is then.
 */
final readonly class RunExportJob implements DataTransferJobMessage, DescribedJob, ReplayableJob
{
    use DataTransferJobMessageTrait;

    public function __construct(
        public int $exportJobId,
        public int $postponements = 0,
    ) {
    }

    public function jobId(): int
    {
        return $this->exportJobId;
    }

    public function jobTable(): JobTable
    {
        return JobTable::Export;
    }
}
