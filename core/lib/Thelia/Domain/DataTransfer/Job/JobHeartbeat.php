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

use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Messenger\Transport\ShopDatabaseConnection;

/**
 * Tells, as a job goes, that a worker is still on it.
 *
 * Written through the second connection to the shop database, so it is seen at once
 * even while the job writes inside a transaction of its own, and never holds a lock
 * on the row of the job: {@see JobClaim} reads it to tell a job still working from
 * one a dead worker left behind.
 */
final readonly class JobHeartbeat
{
    public function __construct(
        private ShopDatabaseConnection $connection,
    ) {
    }

    public function beat(JobTable $table, int $jobId): void
    {
        $connection = $this->connection->get();

        // Best effort: a beat waits a second at most for a row someone else holds (a
        // caller that runs the job inside a transaction of its own), and a missed beat
        // only means the job looks idle a little sooner.
        try {
            $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            $connection->executeStatement(
                'UPDATE `'.$table->value.'` SET `updated_at` = ? WHERE `id` = ?',
                [(new \DateTimeImmutable())->format('Y-m-d H:i:s'), $jobId],
            );
        } catch (\Throwable $exception) {
            Tlog::getInstance()->addDebug(\sprintf('No sign of life written for %s: %s', $table->describe($jobId), JobFailureMessage::forLog($exception)));
        } finally {
            try {
                $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = DEFAULT');
            } catch (\Throwable) {
                // The connection is gone: the next one starts with the default.
            }
        }
    }
}
