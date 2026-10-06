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

use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;

/**
 * Lets one run, and one only, take an export or an import job.
 *
 * A queue may hand the same job to two workers: the Doctrine transport hands it
 * again once its redeliver timeout has passed, and an administrator may replay it
 * while it still runs. The status is switched to running by a single conditional
 * UPDATE, so the second one finds the job taken and leaves it alone. A job left
 * running by a worker that died is taken again once it has run for longer than the
 * redeliver timeout, the delay after which the transport hands it again. That time is
 * counted from the last sign of life of the job, which its handler gives as it goes,
 * not from its start: a long export that is still writing is never taken from under
 * the worker running it.
 */
final readonly class JobClaim
{
    /** The default redeliver timeout of the Doctrine transport. */
    public const STALE_AFTER_SECONDS = 3600;

    /**
     * @param 'export_job'|'import_job' $table
     *
     * @return bool true when this run owns the job now
     */
    public function claim(string $table, int $jobId): bool
    {
        $now = new \DateTimeImmutable();

        $statement = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME)->prepare(
            'UPDATE `'.$table.'` SET `status` = :running, `started_at` = :now, `finished_at` = NULL, `error` = NULL, `updated_at` = :updated'
            .' WHERE `id` = :id AND (`status` IN (:queued, :failed) OR (`status` = :stillRunning AND `updated_at` < :stale))',
        );
        $statement->execute([
            'running' => JobStatus::RUNNING->value,
            'now' => $now->format('Y-m-d H:i:s'),
            'updated' => $now->format('Y-m-d H:i:s'),
            'id' => $jobId,
            'queued' => JobStatus::QUEUED->value,
            'failed' => JobStatus::FAILED->value,
            'stillRunning' => JobStatus::RUNNING->value,
            'stale' => $now->modify(\sprintf('-%d seconds', self::STALE_AFTER_SECONDS))->format('Y-m-d H:i:s'),
        ]);

        return 1 === $statement->rowCount();
    }
}
