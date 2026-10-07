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
 * The row of an export or an import job, as its lifecycle handles it.
 */
interface DataTransferJob
{
    /**
     * @return int|null
     */
    public function getId();

    public function getJobStatus(): JobStatus;

    /**
     * The table of the row: what the claim and the signs of life update.
     *
     * @return 'export_job'|'import_job'
     */
    public function tableName(): string;

    /**
     * Records that the job failed, with what the administrator reads of it.
     */
    public function markFailed(string $error): void;

    /**
     * Reads the row again: another process may have changed it.
     */
    public function refresh(): void;
}
