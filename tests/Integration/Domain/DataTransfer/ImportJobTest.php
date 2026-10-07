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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\DataTransfer\Exception\UploadRefusedException;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Domain\DataTransfer\Job\JobClaim;
use Thelia\Domain\DataTransfer\Job\JobHeartbeat;
use Thelia\Domain\DataTransfer\Job\JobLifecycle;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Domain\DataTransfer\Job\RunImportJobHandler;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Messenger\Transport\ConfiguredQueues;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Model\Import;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * An import asked for in the back office is a job: its uploaded file is kept out of
 * the cache until the job has run, and the row says how many rows it changed and
 * which ones it refused.
 */
final class ImportJobTest extends IntegrationTestCase
{
    private ProductSaleElements $combination;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $this->combination = $factory->productSaleElement($product, ['quantity' => 5]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testWithoutAQueueTheImportIsDoneWhenTheLauncherReturns(): void
    {
        $job = $this->getService(ImportJobLauncher::class)->launch($this->stockImport(), $this->upload(42), 'stock.csv');

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertSame(1, $job->getImportedRows());
        self::assertSame([], $job->getRowErrorList());
        self::assertSame(42.0, (float) $this->reloadedQuantity());
        self::assertFileDoesNotExist($this->storage()->pathOf($job), 'The uploaded file goes once the import is done.');
    }

    public function testWithAQueueTheFileWaitsOutsideTheCacheForAWorker(): void
    {
        $queue = $this->queue();

        $job = $this->launcherWith($queue)->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $this->storage()->pathOf($job);

        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());
        self::assertStringStartsWith(ImportStorage::DIRECTORY, (string) $job->getFilePath());
        self::assertStringNotContainsString(THELIA_CACHE_DIR, $this->storage()->pathOf($job));
        self::assertFileExists($this->storage()->pathOf($job));
        self::assertSame(5.0, (float) $this->reloadedQuantity());

        $this->handler()(new RunImportJob($job->getId()));
        $job->reload();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertSame(17.0, (float) $this->reloadedQuantity());
    }

    public function testTheRowsTheImportRefusesAreKeptWithTheJob(): void
    {
        $job = $this->getService(ImportJobLauncher::class)->launch($this->stockImport(), $this->upload(3, extraRow: "999999999,3\n"), 'stock.csv');

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertCount(1, $job->getRowErrorList());
        self::assertStringContainsString('999999999', $job->getRowErrorList()[0]);
    }

    public function testAFinishedImportIsNotRunAgain(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $this->storage()->pathOf($job);
        $this->handler()(new RunImportJob($job->getId()));

        $this->combination->setQuantity(8)->save($this->getPropelConnection());
        $this->handler()(new RunImportJob($job->getId()));

        self::assertSame(8.0, (float) $this->reloadedQuantity(), 'A job delivered twice changes the catalog once.');
    }

    public function testAnImportWhoseFileIsGoneFailsAndSaysWhy(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        unlink($this->storage()->pathOf($job));

        try {
            $this->handler()(new RunImportJob($job->getId()));
            self::fail('A failed import must reach the failure transport.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertStringContainsString('no longer on the server', (string) $job->getError());
    }

    public function testAJobRunningElsewhereIsNotRunASecondTime(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $this->storage()->pathOf($job);
        $job->setStatus(JobStatus::RUNNING->value)->setStartedAt(new \DateTime('-5 minutes'))->save($this->getPropelConnection());

        $this->handler()(new RunImportJob($job->getId()));
        $job->reload();

        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
        self::assertSame(5.0, (float) $this->reloadedQuantity());
        self::assertFileExists($this->storage()->pathOf($job));
    }

    /**
     * As for an export: a message finding the import running looks at it again later,
     * and never restarts an import that failed meanwhile.
     */
    public function testAnImportRunningElsewhereIsLookedAtAgainButNeverRestartedOnceFailed(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $this->storage()->pathOf($job);
        $job->setStatus(JobStatus::RUNNING->value)->save($this->getPropelConnection());

        $queue = $this->recordingQueue();
        $this->handlerWith($queue)(new RunImportJob($job->getId()));
        self::assertEquals([new RunImportJob($job->getId(), 1)], $queue->kept);

        $job->setStatus(JobStatus::FAILED->value)->save($this->getPropelConnection());
        $this->handlerWith($this->queue())(new RunImportJob($job->getId(), 1));
        $job->reload();

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(5.0, $this->reloadedQuantity());
    }

    /**
     * However long the name it was uploaded under, the path the row keeps fits its
     * column, relative to the project.
     */
    /**
     * A name of a hundred Chinese characters is three hundred bytes: cut in bytes, on a
     * whole character, it still fits what a file system takes.
     */
    public function testANameInAnotherScriptIsCutInBytes(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), str_repeat('库存', 60).'.csv');
        $this->files[] = $this->storage()->pathOf($job);

        self::assertFileExists($this->storage()->pathOf($job));
        self::assertLessThanOrEqual(255, \strlen(basename($this->storage()->pathOf($job))));
        self::assertTrue(mb_check_encoding((string) $job->getFileName(), 'UTF-8'));
    }

    /**
     * Without a queue a failed import is kept nowhere it could be replayed from: its
     * file goes at once rather than a month later.
     */
    public function testWithoutAQueueTheFileOfAFailedImportGoesAtOnce(): void
    {
        $path = sys_get_temp_dir().'/import-job-'.uniqid('', true).'.csv';
        file_put_contents($path, "id,quantity\n1,2\n");
        $this->files[] = $path;

        $job = $this->getService(ImportJobLauncher::class)->launch($this->stockImport(), new File($path), 'stock.csv');

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertFileDoesNotExist($this->storage()->pathOf($job));
    }

    /**
     * Without a queue nobody replays the import: a file that cannot be deleted is left to
     * the purge, and the job still says it failed instead of running for ever.
     */
    public function testAFailedImportWhoseFileCannotBeDeletedStillSaysItFailed(): void
    {
        if (\function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('Run as root, the file can always be deleted.');
        }

        $path = sys_get_temp_dir().'/import-job-'.uniqid('', true).'.csv';
        file_put_contents($path, "id,quantity\n1,2\n");
        $this->files[] = $path;
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), new File($path), 'stock.csv');
        $directory = \dirname($this->storage()->pathOf($job));
        $handler = new RunImportJobHandler(
            $this->getService(ImportHandler::class),
            $this->getService(JobHeartbeat::class),
            $this->inlineLifecycle(),
            $this->storage(),
        );

        chmod($directory, 0o555);

        try {
            $handler(new RunImportJob($job->getId()));
            self::fail('A failed import must reach the failure transport.');
        } catch (UnrecoverableMessageHandlingException) {
        } finally {
            chmod($directory, 0o755);
            $this->storage()->discardFileOf($job);
        }

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
    }

    /**
     * A failure the row cannot record (the row is gone, the database refuses) still sets
     * the job aside, with a reason that quotes no SQL.
     */
    public function testAFailureTheRowCannotRecordStillSetsTheJobAside(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(3), 'stock.csv');
        $this->storage()->discardFileOf($job);
        $job->delete();

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $this->lifecycle($this->queue())->fail($job, new \RuntimeException('SQLSTATE[23000]: buyer@example.com'));
    }

    /**
     * The outcome a run wrote in memory before failing went with its transaction: the
     * failed row says nothing of it.
     */
    public function testAFailedJobKeepsNothingItsRunLeftInMemory(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(3), 'stock.csv');
        $this->storage()->discardFileOf($job);
        $job->setImportedRows(42)->setStatus(JobStatus::DONE->value);

        try {
            $this->lifecycle($this->queue())->fail($job, new \RuntimeException('rolled back'));
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(0, (int) $job->getImportedRows());
    }

    public function testAVeryLongFileNameStillFitsTheRow(): void
    {
        $name = str_repeat('inventaire-entrepot-', 15).'stock.csv';

        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), $name);
        $this->files[] = $this->storage()->pathOf($job);

        self::assertLessThanOrEqual(255, \strlen((string) $job->getFilePath()));
        self::assertStringStartsWith(ImportStorage::DIRECTORY, (string) $job->getFilePath());
        self::assertStringEndsWith('.csv', (string) $job->getFileName());
        self::assertFileExists($this->storage()->pathOf($job));
    }

    /**
     * The queue refuses the job: the row says so, and the uploaded file, which may hold
     * personal data, does not stay behind.
     */
    public function testAJobTheQueueRefusesIsFailedAndItsFileDeleted(): void
    {
        $refusingQueue = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \RuntimeException('The queue server is unreachable.');
            }
        };

        try {
            $this->launcherWith($refusingQueue)->launch($this->stockImport(), $this->upload(17), 'stock.csv');
            self::fail('The caller must learn the import was not queued.');
        } catch (\RuntimeException) {
        }

        $job = ImportJobQuery::create()->orderById('desc')->findOne();
        self::assertNotNull($job);
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(JobLifecycle::NOT_QUEUED, $job->getError());
        self::assertFileDoesNotExist($this->storage()->pathOf($job));
    }

    /**
     * A refused row quotes the cell it refused, as the file gave it: a file saved in
     * Latin-1 is not valid UTF-8, and the import is still recorded as done.
     */
    public function testARefusedRowThatIsNotUtf8IsStillRecorded(): void
    {
        $job = $this->getService(ImportJobLauncher::class)->launch($this->stockImport(), $this->upload(3, extraRow: $this->combination->getId().",d\xE9fectueux\n"), 'stock.csv');

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertCount(1, $job->getRowErrorList());
        self::assertStringContainsString('fectueux', $job->getRowErrorList()[0]);
    }

    /**
     * A caller that runs the import inside a transaction of its own keeps it: a failed
     * import does not leave it unable to commit.
     */
    public function testAFailedImportLeavesTheTransactionOfItsCallerUsable(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        unlink($this->storage()->pathOf($job));
        $connection = $this->getPropelConnection();
        $depth = $connection->getNestedTransactionCount();

        try {
            $this->handler()(new RunImportJob($job->getId()));
        } catch (UnrecoverableMessageHandlingException) {
        }

        self::assertSame($depth, $connection->getNestedTransactionCount());
        self::assertTrue($connection->isCommitable());
    }

    /**
     * An archive is extracted next to the upload: the extracted copy goes once the
     * import is over, rather than piling up in the import storage.
     */
    public function testTheExtractedCopyOfAnArchiveIsRemoved(): void
    {
        $directory = sys_get_temp_dir().'/import-archive-'.uniqid('', true);
        mkdir($directory);
        $archive = new \ZipArchive();
        $archive->open($directory.'/stock.zip', \ZipArchive::CREATE);
        $archive->addFromString('stock.csv', "id,stock\n".$this->combination->getId().",23\n");
        $archive->close();

        try {
            $this->getService(ImportHandler::class)->import($this->stockImport(), new File($directory.'/stock.zip'));

            self::assertSame(23.0, $this->reloadedQuantity());
            self::assertSame(['stock.zip'], array_values(array_diff((array) scandir($directory), ['.', '..'])));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * An archive whose files all sit in a folder cannot be imported: its extracted
     * copy still goes.
     */
    public function testTheExtractedCopyGoesEvenWhenTheArchiveCannotBeImported(): void
    {
        $directory = sys_get_temp_dir().'/import-archive-'.uniqid('', true);
        mkdir($directory);
        $archive = new \ZipArchive();
        $archive->open($directory.'/stock.zip', \ZipArchive::CREATE);
        $archive->addFromString('nested/stock.csv', "id,stock\n".$this->combination->getId().",23\n");
        $archive->close();

        try {
            $this->getService(ImportHandler::class)->import($this->stockImport(), new File($directory.'/stock.zip'));
            self::fail('Nothing at the root of the archive can be imported.');
        } catch (FormValidationException) {
        } finally {
            $left = array_values(array_diff((array) scandir($directory), ['.', '..']));
            (new Filesystem())->remove($directory);
        }

        self::assertSame(['stock.zip'], $left);
    }

    /**
     * The path comes from the row: a file outside the import storage is never read.
     */
    public function testAFileOutsideTheImportStorageIsNeverRead(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $this->storage()->pathOf($job);
        $outside = (string) tempnam(sys_get_temp_dir(), 'import-outside');
        file_put_contents($outside, "id,stock\n".$this->combination->getId().",99\n");
        $this->files[] = $outside;
        $job->setFilePath($outside)->save($this->getPropelConnection());

        try {
            $this->handler()(new RunImportJob($job->getId()));
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame('The file of this import is not in the import storage.', $job->getError());
        self::assertSame(5.0, $this->reloadedQuantity());
    }

    public function testAFileThatDoesNotParseIsTheAdministratorsToFix(): void
    {
        $path = sys_get_temp_dir().'/import-broken-'.uniqid('', true).'.json';
        file_put_contents($path, '[{"id": 1, "stock": ');
        $this->files[] = $path;

        $this->expectException(UploadRefusedException::class);
        $this->expectExceptionMessage('check its content');

        $this->getService(ImportHandler::class)->import($this->stockImport(), new File($path));
    }

    /**
     * An archive with nothing in it says it holds nothing the shop reads, not a
     * server error.
     */
    public function testAnEmptyArchiveIsRefusedForWhatItIs(): void
    {
        $directory = sys_get_temp_dir().'/import-archive-'.uniqid('', true);
        mkdir($directory);
        $archive = new \ZipArchive();
        $archive->open($directory.'/stock.zip', \ZipArchive::CREATE);
        $archive->addEmptyDir('nothing');
        $archive->close();

        try {
            $this->getService(ImportHandler::class)->import($this->stockImport(), new File($directory.'/stock.zip'));
            self::fail('An empty archive cannot be imported.');
        } catch (UploadRefusedException) {
            $this->addToAssertionCount(1);
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    public function testAJobWhoseRowIsGoneFailsForGood(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);

        $this->handler()(new RunImportJob(999999999));
    }

    /**
     * The import tells how far it got, which is how its job shows a worker is still on
     * it: a long import is never taken from under that worker.
     */
    public function testTheImportReportsItsProgress(): void
    {
        $reported = [];

        $this->getService(ImportHandler::class)->import(
            $this->stockImport(),
            $this->upload(17),
            null,
            static function (int $rows) use (&$reported): void {
                $reported[] = $rows;
            },
        );

        self::assertSame([1], $reported);
    }

    public function testAFileTheShopDoesNotReadIsRefusedBeforeAnyJobIsRecorded(): void
    {
        $this->expectException(\Throwable::class);

        $this->getService(ImportJobLauncher::class)->launch($this->stockImport(), $this->upload(1), 'stock.exe');
    }

    private function stockImport(): Import
    {
        $import = ImportQuery::create()->findOneByRef('thelia.import.stock');

        if (null === $import) {
            self::markTestSkipped('The test database has no stock import.');
        }

        return $import;
    }

    private function upload(int $stock, string $extraRow = ''): File
    {
        $path = sys_get_temp_dir().'/import-job-'.uniqid('', true).'.csv';
        file_put_contents($path, "id,stock\n".$this->combination->getId().','.$stock."\n".$extraRow);
        $this->files[] = $path;

        return new File($path);
    }

    private function reloadedQuantity(): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($this->combination->getId(), $this->getPropelConnection())?->getQuantity();
    }

    private function launcherWith(MessageBusInterface $bus): ImportJobLauncher
    {
        return new ImportJobLauncher(
            $this->getService(ImportHandler::class),
            $this->lifecycle($bus),
            $this->storage(),
        );
    }

    private function handler(): RunImportJobHandler
    {
        return $this->getService(RunImportJobHandler::class);
    }

    private function handlerWith(MessageBusInterface $bus): RunImportJobHandler
    {
        return new RunImportJobHandler(
            $this->getService(ImportHandler::class),
            $this->getService(JobHeartbeat::class),
            $this->lifecycle($bus),
            $this->storage(),
        );
    }

    private function lifecycle(MessageBusInterface $bus): JobLifecycle
    {
        return new JobLifecycle(new JobClaim(), $bus, new ConfiguredQueues($this->getService(ShopDatabaseConnection::class), 'doctrine://default', 'doctrine://default?queue_name=heavy', 'doctrine://default?queue_name=failed'));
    }

    private function inlineLifecycle(): JobLifecycle
    {
        return new JobLifecycle(new JobClaim(), $this->queue(), new ConfiguredQueues($this->getService(ShopDatabaseConnection::class), 'sync://', 'sync://', 'doctrine://default?queue_name=failed'));
    }

    private function storage(): ImportStorage
    {
        return $this->getService(ImportStorage::class);
    }

    /**
     * @return MessageBusInterface&object{kept: list<object>}
     */
    private function recordingQueue(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $kept = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->kept[] = $message;

                return Envelope::wrap($message, $stamps);
            }
        };
    }

    private function queue(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return Envelope::wrap($message, $stamps);
            }
        };
    }
}
