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
            if (null !== $job && is_file($job->getStoredFilePath())) {
                unlink($job->getStoredFilePath());
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

    private function launch(int $stock): \Thelia\Model\ImportJob
    {
        $path = sys_get_temp_dir().'/import-atomicity-'.uniqid('', true).'.csv';
        file_put_contents($path, "id,stock\n".$this->combination->getId().','.$stock."\n");

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
