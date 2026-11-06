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
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Model\Category;
use Thelia\Model\ImportJob;
use Thelia\Model\ImportJobQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\IntegrationTestCase;

/**
 * A stock import tells the listeners of the stock levels what it changed, once, when it is over:
 * one change per combination (the stock before the first row to the stock after the last), the
 * threshold crossing included, and nothing for a row it refused or an import it rolled back.
 *
 * Played through the job that carries an import, without the transaction the other tests run in
 * (the job holds its own, which is what defers the announcement until it is over). What the test
 * writes is deleted by hand afterwards.
 */
final class ImportStockAnnouncementTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private Category $category;

    private Product $product;

    private ProductSaleElements $combination;

    private ProductSaleElements $other;

    /** @var list<StockLevelChange> */
    private array $changes = [];

    /** @var list<LowStockReachedEvent> */
    private array $signals = [];

    /** @var array<string, \Closure> */
    private array $listeners = [];

    /** @var list<int> */
    private array $jobs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->category = $factory->category();
        $this->product = $factory->product($this->category, $factory->taxRule(), $factory->currency());
        $this->combination = $factory->productSaleElement($this->product, ['quantity' => 5]);
        $this->other = $factory->productSaleElement($this->product, ['quantity' => 5]);

        $this->listeners = [
            TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED => function (StockLevelChangedEvent $event): void {
                $this->changes = [...$this->changes, ...$event->getChanges()];
            },
            TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED => function (LowStockReachedEvent $event): void {
                $this->signals[] = $event;
                // Stops the signal before the default e-mail listener.
                $event->stopPropagation();
            },
        ];

        foreach ($this->listeners as $eventName => $listener) {
            $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 1000);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as $eventName => $listener) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }

        $this->getService(StockLevelAnnouncer::class)->reset();

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

    public function testACombinationWrittenByTwoRowsIsToldOnceFromTheFirstStockToTheLast(): void
    {
        $job = $this->launch("id,stock\n".$this->combination->getId().",20\n".$this->other->getId().",8\n".$this->combination->getId().",30\n");

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        self::assertEqualsCanonicalizing([
            new StockLevelChange((int) $this->combination->getId(), 5.0, 30.0),
            new StockLevelChange((int) $this->other->getId(), 5.0, 8.0),
        ], $this->changes);
    }

    public function testARowRefusedForItsGtinIsNotTold(): void
    {
        $job = $this->launch("id,stock,ean\n".$this->combination->getId().",42,\n".$this->other->getId().",7,4006381333932\n");

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        self::assertCount(1, $job->getRowErrorList());
        self::assertEquals([new StockLevelChange((int) $this->combination->getId(), 5.0, 42.0)], $this->changes);
    }

    /**
     * Outside a job there is no transaction to wait for: the changes collected by the rows are the
     * ones told, at once, so a refused row must not be among them.
     */
    public function testARowRefusedForItsGtinIsNotToldWhenTheImportRunsOutsideAJob(): void
    {
        $path = sys_get_temp_dir().'/import-stock-announcement-'.uniqid('', true).'.csv';
        file_put_contents($path, "id,stock,ean\n".$this->combination->getId().",42,\n".$this->other->getId().",7,4006381333932\n");
        $import = ImportQuery::create()->findOneByRef('thelia.import.stock');
        self::assertNotNull($import);

        try {
            $event = $this->getService(ImportHandler::class)->import($import, new File($path));
        } finally {
            unlink($path);
        }

        self::assertCount(1, $event->getErrors());
        self::assertEquals([new StockLevelChange((int) $this->combination->getId(), 5.0, 42.0)], $this->changes);
    }

    public function testAnImportThatFailsAfterWritingItsRowsTellsNothing(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $failAtTheEnd = static function (): void {
            throw new \RuntimeException('The worker was stopped.');
        };
        $dispatcher->addListener(TheliaEvents::IMPORT_FINISHED, $failAtTheEnd);

        try {
            $job = $this->launch("id,stock\n".$this->combination->getId().",42\n");
        } finally {
            $dispatcher->removeListener(TheliaEvents::IMPORT_FINISHED, $failAtTheEnd);
        }

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame([], $this->changes);
    }

    public function testAnImportThatTakesTheStockUnderTheThresholdSignalsTheCrossing(): void
    {
        $this->combination->setQuantity(30.0)->setLowStockThreshold(10.0)->save();

        $job = $this->launch("id,stock\n".$this->combination->getId().",2\n");

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
        self::assertCount(1, $this->signals);
        self::assertSame((int) $this->combination->getId(), $this->signals[0]->getProductSaleElementsId());
        self::assertSame(2.0, $this->signals[0]->getQuantity());
    }

    private function launch(string $content): ImportJob
    {
        $path = sys_get_temp_dir().'/import-stock-announcement-'.uniqid('', true).'.csv';
        file_put_contents($path, $content);

        $import = ImportQuery::create()->findOneByRef('thelia.import.stock');
        self::assertNotNull($import);

        $job = $this->getService(ImportJobLauncher::class)->launch($import, new File($path), 'stock.csv');
        $this->jobs[] = (int) $job->getId();

        // What the job's own transaction held back goes out when its request or message is over.
        $this->getService(StockLevelAnnouncer::class)->flush();

        return $job;
    }
}
