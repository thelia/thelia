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

/**
 * The table a job lives in: the only table names ever written into the SQL that takes,
 * marks or keeps a job alive.
 */
enum JobTable: string
{
    case Export = 'export_job';
    case Import = 'import_job';

    /**
     * The job as the failed jobs screen names it: "Export #4", "Import #12".
     */
    public function describe(int $jobId): string
    {
        return \sprintf('%s #%d', $this->name, $jobId);
    }
}
