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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Thelia\Core\Event\ImportEvent;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\ImportJob;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\Map\ImportJobTableMap;

/**
 * Reads the file of one import job into the shop, and records what came of it.
 *
 * A finished import is never run again, and one being run is not run a second time at
 * once ({@see JobClaim}): a queue may deliver a job twice, and an import changes the
 * catalog. A failed one goes straight to the failure transport ({@see JobLifecycle})
 * and runs again from the first row when it is replayed: nothing of the failed run was
 * kept. The uploaded file is deleted once the import is done, and kept while it may be
 * replayed.
 */
#[AsMessageHandler]
final readonly class RunImportJobHandler
{
    public function __construct(
        private ImportHandler $importHandler,
        private JobHeartbeat $heartbeat,
        private JobLifecycle $lifecycle,
        private ImportStorage $storage,
    ) {
    }

    public function __invoke(RunImportJob $message): void
    {
        $job = $this->lifecycle->take($message, static fn (int $id): ?ImportJob => ImportJobQuery::create()->findPk($id));

        if (!$job instanceof ImportJob) {
            return;
        }

        try {
            $job->setImportedRows(0)->setRowErrors(null)->save();
        } catch (\Throwable $exception) {
            $this->lifecycle->fail($message, $job, $exception);
        }

        // The whole import is one transaction, which also records its outcome: stopped
        // half way (an error, a worker killed, a deployment), it leaves the catalog as
        // it was, and the catalog and the row never disagree. A caller that already
        // holds a transaction keeps it: committing or rolling back is its call, a
        // nested Propel transaction would only be a counter.
        $connection = Propel::getWriteConnection(ImportJobTableMap::DATABASE_NAME);
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $this->recordOutcome($job, $this->run($job));

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            self::rollBackOwned($connection, $ownsTransaction);

            // Kept while the job can be replayed; without a queue it never can be.
            if (!$this->lifecycle->keepsFailedJobs()) {
                $this->discardFileOf($job);
            }

            $this->lifecycle->fail($message, $job, $exception);
        }

        $this->discardFileOf($job);
    }

    /**
     * A file that cannot be deleted is the purge's to sweep, not a reason to say
     * anything else of the job than how it ended.
     */
    private function discardFileOf(ImportJob $job): void
    {
        try {
            $this->storage->discardFileOf($job);
        } catch (\Throwable $leftBehind) {
            Tlog::getInstance()->addWarning(\sprintf('The file of import job %d was left behind: %s', $job->getId(), JobFailureMessage::forLog($leftBehind)));
        }
    }

    private function run(ImportJob $job): ImportEvent
    {
        $import = $job->getImport() ?? throw new JobRefusedException('The import of this job no longer exists.');
        $path = $this->storage->pathOf($job);

        if (!is_file($path)) {
            throw new JobRefusedException('The uploaded file of this import is no longer on the server.');
        }

        // The path comes from the row: only a file of the import storage is read.
        if (!$this->storage->holds($path)) {
            throw new JobRefusedException('The file of this import is not in the import storage.');
        }

        $jobId = (int) $job->getId();
        $heartbeat = $this->heartbeat;

        return $this->importHandler->import(
            $import,
            new File($path),
            $job->getLang(),
            // A sign of life, outside the transaction, so a long import is never taken
            // from the worker running it.
            static function () use ($heartbeat, $jobId): void {
                $heartbeat->beat(JobTable::Import, $jobId);
            },
        );
    }

    private function recordOutcome(ImportJob $job, ImportEvent $event): void
    {
        $job->setStatus(JobStatus::DONE->value)
            ->setImportedRows((int) $event->getImport()->getImportedRows())
            ->setRowErrorList(array_values($event->getErrors()))
            ->setFinishedAt(new \DateTime())
            ->save();
    }

    private static function rollBackOwned(ConnectionInterface $connection, bool $ownsTransaction): void
    {
        if (!$ownsTransaction || !$connection->inTransaction()) {
            return;
        }

        // The whole of it: a module's import may have left a nested transaction open,
        // which a plain rollBack() would only count down.
        if ($connection instanceof ConnectionWrapper) {
            $connection->forceRollBack();

            return;
        }

        $connection->rollBack();
    }
}
