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

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunImportJob;
use Thelia\Domain\DataTransfer\Job\RunImportJobHandler;
use Thelia\Model\Import;
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
        self::assertFileDoesNotExist($job->getFilePath(), 'The uploaded file goes once the import is done.');
    }

    public function testWithAQueueTheFileWaitsOutsideTheCacheForAWorker(): void
    {
        $queue = $this->queue();

        $job = $this->launcherWith($queue)->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        $this->files[] = $job->getFilePath();

        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());
        self::assertStringContainsString(ImportJobLauncher::STORAGE_DIRECTORY, $job->getFilePath());
        self::assertStringNotContainsString(THELIA_CACHE_DIR, $job->getFilePath());
        self::assertFileExists($job->getFilePath());
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
        $this->files[] = $job->getFilePath();
        $this->handler()(new RunImportJob($job->getId()));

        $this->combination->setQuantity(8)->save($this->getPropelConnection());
        $this->handler()(new RunImportJob($job->getId()));

        self::assertSame(8.0, (float) $this->reloadedQuantity(), 'A job delivered twice changes the catalog once.');
    }

    public function testAnImportWhoseFileIsGoneFailsAndSaysWhy(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->stockImport(), $this->upload(17), 'stock.csv');
        unlink($job->getFilePath());

        try {
            $this->handler()(new RunImportJob($job->getId()));
            self::fail('A failed import must reach the failure transport.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertStringContainsString('no longer on the server', (string) $job->getError());
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
        return new ImportJobLauncher($this->getService(ImportHandler::class), $bus, (string) static::getContainer()->getParameter('kernel.project_dir'));
    }

    private function handler(): RunImportJobHandler
    {
        return $this->getService(RunImportJobHandler::class);
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
