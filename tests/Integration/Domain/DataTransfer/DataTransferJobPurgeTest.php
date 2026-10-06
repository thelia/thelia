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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Domain\DataTransfer\EventListener\PurgeExportCacheListener;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Service\DataTransferJobPurger;
use Thelia\Model\ExportJob;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\ImportJob;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\ImportQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The jobs of the exports and imports go with maintenance:purge after a week; an
 * import that never ran takes the file it still holds with it.
 */
final class DataTransferJobPurgeTest extends IntegrationTestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = THELIA_ROOT.ImportJobLauncher::STORAGE_DIRECTORY.'/purge-test-'.uniqid();
        mkdir($this->storage, 0o777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->storage);

        parent::tearDown();
    }

    public function testAWeekOldJobIsPurgedWithTheFileItHolds(): void
    {
        $file = $this->storedFile();
        $oldImport = $this->importJob('-8 days', $file);
        $recentImport = $this->importJob('-1 day', '/nowhere.csv');
        $oldExport = $this->exportJob('-8 days');
        $recentExport = $this->exportJob('-1 day');

        $this->getService(PurgeExportCacheListener::class)->onMaintenancePurge(new MaintenancePurgeEvent(false));

        self::assertNull(ImportJobQuery::create()->findPk($oldImport));
        self::assertNotNull(ImportJobQuery::create()->findPk($recentImport));
        self::assertFileDoesNotExist((string) $file);
        self::assertNull(ExportJobQuery::create()->findPk($oldExport));
        self::assertNotNull(ExportJobQuery::create()->findPk($recentExport));
    }

    /**
     * A failed job can still be replayed from the failures for a month: its row stays
     * as long, or the replay would find nothing to run.
     */
    public function testAFailedJobStaysAsLongAsTheFailures(): void
    {
        $failedImport = $this->importJob('-8 days', '/nowhere.csv', JobStatus::FAILED);
        $expiredImport = $this->importJob('-40 days', '/nowhere.csv', JobStatus::FAILED);
        $failedExport = $this->exportJob('-8 days', JobStatus::FAILED);
        $expiredExport = $this->exportJob('-40 days', JobStatus::FAILED);

        $this->getService(PurgeExportCacheListener::class)->onMaintenancePurge(new MaintenancePurgeEvent(false));

        self::assertNotNull(ImportJobQuery::create()->findPk($failedImport));
        self::assertNull(ImportJobQuery::create()->findPk($expiredImport));
        self::assertNotNull(ExportJobQuery::create()->findPk($failedExport));
        self::assertNull(ExportJobQuery::create()->findPk($expiredExport));
    }

    public function testADryRunKeepsEverything(): void
    {
        $file = $this->storedFile();
        $oldImport = $this->importJob('-8 days', $file);
        $oldExport = $this->exportJob('-8 days');

        $event = new MaintenancePurgeEvent(true);
        $this->getService(PurgeExportCacheListener::class)->onMaintenancePurge($event);

        self::assertNotNull(ImportJobQuery::create()->findPk($oldImport));
        self::assertNotNull(ExportJobQuery::create()->findPk($oldExport));
        self::assertFileExists((string) $file);
        self::assertStringContainsString('1 to delete', implode("\n", $event->getResults()));
    }

    /**
     * The path comes from the row: a row pointing outside the import storage loses its
     * row, never the file it points to.
     */
    public function testAFileOutsideTheImportStorageIsNeverDeleted(): void
    {
        $outside = (string) tempnam(sys_get_temp_dir(), 'import-job-purge');
        $job = $this->importJob('-8 days', $outside);

        try {
            $this->getService(DataTransferJobPurger::class)->purgeImportJobs();

            self::assertNull(ImportJobQuery::create()->findPk($job));
            self::assertFileExists($outside);
        } finally {
            unlink($outside);
        }
    }

    /**
     * A file older than any job can be has no row left to be deleted with: an archive
     * extracted by an import that was killed, a row deleted by hand. The disk is swept.
     */
    public function testFilesLeftBehindInTheImportStorageAreSwept(): void
    {
        $leftBehind = $this->storedFile('extracted/stock.csv');
        touch($leftBehind, (int) strtotime('-40 days'));
        $recent = $this->storedFile();

        $purger = $this->getService(DataTransferJobPurger::class);

        self::assertGreaterThanOrEqual(1, $purger->sweepImportStorage(true));
        self::assertFileExists($leftBehind);

        $purger->sweepImportStorage();

        self::assertFileDoesNotExist($leftBehind);
        self::assertFileExists($recent);

        // An empty directory goes once a day old, never while an upload may be about
        // to land in it.
        self::assertDirectoryExists(\dirname($leftBehind));
        touch(\dirname($leftBehind), (int) strtotime('-2 days'));
        $purger->sweepImportStorage();
        self::assertDirectoryDoesNotExist(\dirname($leftBehind));
    }

    private function storedFile(string $name = ''): string
    {
        $path = $this->storage.'/'.('' === $name ? uniqid().'.csv' : $name);
        (new Filesystem())->dumpFile($path, "id,stock\n");

        return $path;
    }

    private function importJob(string $age, string $file, JobStatus $status = JobStatus::QUEUED): int
    {
        $job = (new ImportJob())
            ->setImportId((int) ImportQuery::create()->findOne()?->getId())
            ->setStatus($status->value)
            ->setFilePath($file)
            ->setFileName('stock.csv');
        $job->save($this->getPropelConnection());
        $job->setCreatedAt(new \DateTime($age))->save($this->getPropelConnection());

        return $job->getId();
    }

    private function exportJob(string $age, JobStatus $status = JobStatus::DONE): int
    {
        $job = (new ExportJob())
            ->setExportId((int) ExportQuery::create()->findOne()?->getId())
            ->setStatus($status->value)
            ->setSerializer('thelia.csv');
        $job->save($this->getPropelConnection());
        $job->setCreatedAt(new \DateTime($age))->save($this->getPropelConnection());

        return $job->getId();
    }
}
