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

namespace Thelia\Domain\DataTransfer\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Messenger\FailedMessagePurger;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ImportJobQuery;

/**
 * Deletes the export and import jobs nobody needs any more, and the uploaded files
 * they leave behind.
 *
 * A job outlives its file by a few days, so the back office can still say what was
 * exported or imported. A failed one stays as long as the failed jobs do: it can be
 * replayed from there, and replaying needs its row. An uploaded file is personal data
 * as much as the job is, so it goes with its row; one older than any row can be (an
 * archive extracted by an import that was killed, a file whose row was deleted by
 * hand) is swept from the disk.
 */
final readonly class DataTransferJobPurger
{
    public const JOB_RETENTION_DAYS = 7;

    public function __construct(
        private ImportStorage $storage,
    ) {
    }

    /**
     * @return int the number of jobs deleted, or that would be with $dryRun
     */
    public function purgeExportJobs(bool $dryRun = false): int
    {
        $query = ExportJobQuery::create()->filterExpired(self::JOB_RETENTION_DAYS, FailedMessagePurger::RETENTION_DAYS);

        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * @return int the number of jobs deleted, or that would be with $dryRun
     */
    public function purgeImportJobs(bool $dryRun = false): int
    {
        $jobs = ImportJobQuery::create()->filterExpired(self::JOB_RETENTION_DAYS, FailedMessagePurger::RETENTION_DAYS)->find();

        if (!$dryRun) {
            foreach ($jobs as $job) {
                // An import that never ran still holds the file it was given. The path
                // comes from the row: nothing outside the import storage is deleted.
                $this->storage->discardFileOf($job);

                $job->delete();
            }
        }

        return \count($jobs);
    }

    /**
     * Deletes the files of the import storage older than any job row can be.
     *
     * @return int the number of files deleted, or that would be with $dryRun
     */
    public function sweepImportStorage(bool $dryRun = false): int
    {
        $directory = $this->storage->directory();

        if (!is_dir($directory)) {
            return 0;
        }

        $files = iterator_to_array(
            (new Finder())->files()->in($directory)->ignoreDotFiles(false)->date(\sprintf('before %d days ago', FailedMessagePurger::RETENTION_DAYS)),
            false,
        );

        if ($dryRun) {
            return \count($files);
        }

        $filesystem = new Filesystem();
        $filesystem->remove($files);

        // The day directories, and the directories of extracted archives, once empty
        // and a day old: a fresh one may be about to receive an upload.
        foreach (iterator_to_array((new Finder())->directories()->in($directory)->date('before 1 day ago')->sortByName()->reverseSorting(), false) as $emptyCandidate) {
            if ([] === array_diff((array) scandir($emptyCandidate->getPathname()), ['.', '..'])) {
                $filesystem->remove($emptyCandidate->getPathname());
            }
        }

        return \count($files);
    }
}
