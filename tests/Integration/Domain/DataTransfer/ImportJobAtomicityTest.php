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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Model\Category;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * An import stopped half way leaves the catalog as it was.
 *
 * Played without the transaction the other tests run in: a transaction nested in it
 * would not really be rolled back, and what is under test is precisely that rollback.
 * What the test writes is deleted by hand afterwards.
 */
final class ImportJobAtomicityTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private Category $category;

    private Product $product;

    private ProductSaleElements $combination;

    /** @var list<int> */
    private array $jobs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->category = $factory->category();
        $this->product = $factory->product($this->category, $factory->taxRule(), $factory->currency());
        $this->combination = $factory->productSaleElement($this->product, ['quantity' => 5]);
    }

    protected function tearDown(): void
    {
        foreach ($this->jobs as $jobId) {
            $job = ImportJobQuery::create()->findPk($jobId);
            if (null !== $job && is_file($this->getService(ImportStorage::class)->pathOf($job))) {
                unlink($this->getService(ImportStorage::class)->pathOf($job));
            }
            $job?->delete();
        }

        $this->product->delete();
        $this->category->delete();

        parent::tearDown();
    }

    public function testAnImportThatFailsAfterWritingItsRowsLeavesNothingBehind(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $failAtTheEnd = static function (): void {
            throw new \RuntimeException('The worker was stopped.');
        };
        $dispatcher->addListener(TheliaEvents::IMPORT_FINISHED, $failAtTheEnd);

        try {
            $job = $this->launch(42);
        } finally {
            $dispatcher->removeListener(TheliaEvents::IMPORT_FINISHED, $failAtTheEnd);
        }

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(5.0, $this->quantity(), 'The row written before the failure is rolled back with the rest.');
    }

    public function testAnImportThatGoesThroughIsKept(): void
    {
        $job = $this->launch(42);

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertSame(42.0, $this->quantity());
    }

    /**
     * A row refused for its GTIN is refused alone: saving it rolled back a transaction
     * nested in the import, which must not leave the import unable to commit.
     */
    public function testARowRefusedForItsGtinLeavesTheOthersImported(): void
    {
        $other = $this->createFixtureFactory()->productSaleElement($this->product, ['quantity' => 5]);
        $job = $this->launchCsv("id,stock,ean\n".$this->combination->getId().",42,\n".$other->getId().",7,4006381333932\n");

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        self::assertSame(42.0, $this->quantity());
        self::assertCount(1, $job->getRowErrorList());
    }

    /**
     * Thousands of refused rows do not fit the column that keeps them: the import is
     * still recorded as done, with the first ones and how many more there were.
     */
    public function testThousandsOfRefusedRowsStillLeaveTheImportDone(): void
    {
        $rows = "id,stock\n".$this->combination->getId().",42\n";
        for ($row = 1; $row <= 3000; ++$row) {
            $rows .= (900000000 + $row).",1\n";
        }

        $job = $this->launchCsv($rows);

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        self::assertSame(42.0, $this->quantity());
        self::assertLessThanOrEqual(\Thelia\Model\ImportJob::ROW_ERRORS_MAX_BYTES, \strlen((string) $job->getRowErrors()));
        $errors = $job->getRowErrorList();
        self::assertStringContainsString('more rows were refused', (string) end($errors));
    }

    /**
     * A module's import that catches the failed save of a row has left the import's
     * transaction unable to commit: it is said at that row, nothing imported.
     */
    public function testARowThatCannotBeSavedStopsTheImportWithItsNumber(): void
    {
        $import = ImportQuery::create()->findOneByRef('thelia.import.stock');
        self::assertNotNull($import);
        $handleClass = $import->getHandleClass();
        $import->setHandleClass(\Thelia\Tests\Support\DataTransfer\RowRollingBackImport::class)->save();

        try {
            $job = $this->launchCsv("id,stock\n".$this->combination->getId().",42\n");
        } finally {
            $import->setHandleClass($handleClass)->save();
        }

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame('Row 1 could not be saved: nothing was imported.', $job->getError());
    }

    private function launch(int $stock): \Thelia\Model\ImportJob
    {
        return $this->launchCsv("id,stock\n".$this->combination->getId().','.$stock."\n");
    }

    private function launchCsv(string $content): \Thelia\Model\ImportJob
    {
        $path = sys_get_temp_dir().'/import-atomicity-'.uniqid('', true).'.csv';
        file_put_contents($path, $content);

        $import = ImportQuery::create()->findOneByRef('thelia.import.stock');
        self::assertNotNull($import);

        $job = $this->getService(ImportJobLauncher::class)->launch($import, new File($path), 'stock.csv');
        $this->jobs[] = (int) $job->getId();

        return $job;
    }

    private function quantity(): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($this->combination->getId())?->getQuantity();
    }
}
