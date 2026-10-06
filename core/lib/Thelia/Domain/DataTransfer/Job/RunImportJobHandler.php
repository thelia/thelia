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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Log\Tlog;
use Thelia\Model\ImportJob;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\Map\ImportJobTableMap;

/**
 * Reads the file of one import job into the shop, and records what came of it.
 *
 * A finished import is never run again, and one being run is not run a second time at
 * once ({@see JobClaim}): a queue may deliver a job twice, and an import changes the
 * catalog. A failed one goes straight to the failure transport and runs
 * again from the first row when it is replayed; each row is written on its own, so the
 * rows written before the failure are written a second time, with the same values.
 * The uploaded file is deleted once the import is done, and kept while it may be
 * replayed.
 */
#[AsMessageHandler]
final readonly class RunImportJobHandler
{
    public function __construct(
        private ImportHandler $importHandler,
        private JobHeartbeat $heartbeat,
    ) {
    }

    public function __invoke(RunImportJob $message): void
    {
        $job = ImportJobQuery::create()->findPk($message->importJobId);

        if (!$job instanceof ImportJob) {
            throw new UnrecoverableMessageHandlingException(\sprintf('Import job %d no longer exists.', $message->importJobId));
        }

        if (!JobClaim::claim(ImportJobTableMap::TABLE_NAME, $job->getId())) {
            // Done already, or being run by another worker right now.
            return;
        }

        $job->reload();
        $job->setImportedRows(0)->setRowErrors(null)->save();

        // The whole import is one transaction: stopped half way (an error, a worker
        // killed, a deployment), it leaves the catalog as it was, never half imported.
        $connection = Propel::getWriteConnection(ImportJobTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $import = $job->getImport() ?? throw new \RuntimeException('The import of this job no longer exists.');

            if (!is_file($job->getStoredFilePath())) {
                throw new \RuntimeException('The uploaded file of this import is no longer on the server.');
            }

            $jobId = (int) $job->getId();
            $heartbeat = $this->heartbeat;
            $event = $this->importHandler->import(
                $import,
                new File($job->getStoredFilePath()),
                $job->getLang(),
                // A sign of life, outside the transaction, so a long import is never
                // taken from the worker running it.
                static function () use ($heartbeat, $jobId): void {
                    $heartbeat->beat(ImportJobTableMap::TABLE_NAME, $jobId);
                },
            );

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Tlog::getInstance()->addError(\sprintf('Import job %d failed: %s', $job->getId(), $exception->getMessage()));

            $job->setStatus(JobStatus::FAILED->value)
                ->setError(mb_substr($exception->getMessage(), 0, 2000))
                ->setFinishedAt(new \DateTime())
                ->save();

            throw new UnrecoverableMessageHandlingException(\sprintf('Import job %d failed: %s', $job->getId(), $exception->getMessage()), 0, $exception);
        }

        $job->setStatus(JobStatus::DONE->value)
            ->setImportedRows((int) $event->getImport()->getImportedRows())
            ->setRowErrors([] === $event->getErrors() ? null : json_encode(array_values($event->getErrors()), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR))
            ->setFinishedAt(new \DateTime())
            ->save();

        if (is_file($job->getStoredFilePath())) {
            unlink($job->getStoredFilePath());
        }
    }
}
