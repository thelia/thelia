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
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobClaim;
use Thelia\Domain\DataTransfer\Job\JobHeartbeat;
use Thelia\Domain\DataTransfer\Job\JobLifecycle;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Domain\DataTransfer\Job\RunImportJobHandler;
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
        self::assertFileDoesNotExist($job->getStoredFilePath(), 'The uploaded file goes once the import is done.');
    }

    public function testWithAQueueTheFileWaitsOutsideTheCacheForAWorker(): void
    {
        $queue = $this->queue();

        $job = $this->launcherWith($queue)->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $job->getStoredFilePath();

        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());
        self::assertStringStartsWith(ImportJobLauncher::STORAGE_DIRECTORY, (string) $job->getFilePath());
        self::assertStringNotContainsString(THELIA_CACHE_DIR, $job->getStoredFilePath());
        self::assertFileExists($job->getStoredFilePath());
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
        $this->files[] = $job->getStoredFilePath();
        $this->handler()(new RunImportJob($job->getId()));

        $this->combination->setQuantity(8)->save($this->getPropelConnection());
        $this->handler()(new RunImportJob($job->getId()));

        self::assertSame(8.0, (float) $this->reloadedQuantity(), 'A job delivered twice changes the catalog once.');
    }

    public function testAnImportWhoseFileIsGoneFailsAndSaysWhy(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        unlink($job->getStoredFilePath());

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
        $this->files[] = $job->getStoredFilePath();
        $job->setStatus(JobStatus::RUNNING->value)->setStartedAt(new \DateTime('-5 minutes'))->save($this->getPropelConnection());

        $this->handler()(new RunImportJob($job->getId()));
        $job->reload();

        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
        self::assertSame(5.0, (float) $this->reloadedQuantity());
        self::assertFileExists($job->getStoredFilePath());
    }

    /**
     * As for an export: a message finding the import running looks at it again later,
     * and never restarts an import that failed meanwhile.
     */
    public function testAnImportRunningElsewhereIsLookedAtAgainButNeverRestartedOnceFailed(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $job->getStoredFilePath();
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
    public function testAVeryLongFileNameStillFitsTheRow(): void
    {
        $name = str_repeat('inventaire-entrepot-', 15).'stock.csv';

        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), $name);
        $this->files[] = $job->getStoredFilePath();

        self::assertLessThanOrEqual(255, \strlen((string) $job->getFilePath()));
        self::assertStringStartsWith(ImportJobLauncher::STORAGE_DIRECTORY, (string) $job->getFilePath());
        self::assertStringEndsWith('.csv', (string) $job->getFileName());
        self::assertFileExists($job->getStoredFilePath());
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
        self::assertSame('The job could not be queued. The details are in the server log.', $job->getError());
        self::assertFileDoesNotExist($job->getStoredFilePath());
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
        unlink($job->getStoredFilePath());
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
            new JobLifecycle(new JobClaim(), $bus, 'doctrine://default?queue_name=heavy'),
            (string) static::getContainer()->getParameter('kernel.project_dir'),
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
            new JobLifecycle(new JobClaim(), $bus, 'doctrine://default?queue_name=heavy'),
        );
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
