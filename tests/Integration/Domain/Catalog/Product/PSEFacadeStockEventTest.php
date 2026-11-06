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

namespace Thelia\Tests\Integration\Domain\Catalog\Product;

use Propel\Runtime\Propel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\PSEFacade;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A change of stock written through the facade is told to the listeners of the stock levels once
 * it is written for good, with the stock before and after; inside a transaction of the caller it
 * waits for the end of that transaction, and is never told if the transaction is rolled back.
 */
final class PSEFacadeStockEventTest extends ActionIntegrationTestCase
{
    protected bool $useTransaction = false;

    private ?Product $product = null;

    /** @var list<StockLevelChange> */
    private array $changes = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = function (StockLevelChangedEvent $event): void {
            $this->changes = [...$this->changes, ...$event->getChanges()];
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $this->listener);
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->product->getDefaultSaleElements()->setQuantity(22)->save();
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->dispatcher->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $this->listener);
        }

        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        while ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if (null !== $this->product) {
            $categoryIds = $connection->query('SELECT category_id FROM product_category WHERE product_id = '.(int) $this->product->getId())->fetchAll(\PDO::FETCH_COLUMN);
            $connection->exec('DELETE FROM product WHERE id = '.(int) $this->product->getId());

            if ([] !== $categoryIds) {
                $connection->exec('DELETE FROM category WHERE id IN ('.implode(',', array_map('intval', $categoryIds)).')');
            }
        }

        parent::tearDown();
    }

    public function testEveryChangeOfTheFacadeIsToldWithTheStockBeforeAndAfter(): void
    {
        $facade = $this->getService(PSEFacade::class);
        $id = (int) $this->product?->getDefaultSaleElements()->getId();

        $facade->decreaseStock($id, 3);
        $facade->increaseStock($id, 1);
        $facade->updateStock($id, 50);
        $facade->updateStock($id, 50);

        self::assertEquals(
            [new StockLevelChange($id, 22.0, 19.0), new StockLevelChange($id, 19.0, 20.0), new StockLevelChange($id, 20.0, 50.0)],
            $this->changes,
            'A write that changes nothing tells nothing.',
        );
    }

    public function testAChangeInsideATransactionOfTheCallerWaitsForItsCommit(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $announcer = $this->getService(StockLevelAnnouncer::class);
        $id = (int) $this->product?->getDefaultSaleElements()->getId();
        $connection->beginTransaction();

        try {
            $facade = $this->getService(PSEFacade::class);
            $facade->decreaseStock($id, 3);
            $facade->increaseStock($id, 1);
            $announcer->flush();

            self::assertSame([], $this->changes, 'Nothing is told while the transaction of the caller is open.');
        } finally {
            $connection->commit();
        }

        self::assertSame([], $this->changes, 'The commit tells nothing by itself.');

        $announcer->flush();

        self::assertEquals([new StockLevelChange($id, 22.0, 20.0)], $this->changes, 'Told once the transaction is over, from the stock before the first change to the stock after the last.');

        $announcer->flush();

        self::assertCount(1, $this->changes, 'Told once.');
    }

    public function testAChangeOfATransactionThatWasRolledBackIsNeverTold(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->getService(PSEFacade::class)->decreaseStock((int) $this->product?->getDefaultSaleElements()->getId(), 3);
        } finally {
            $connection->rollBack();
        }

        $this->getService(StockLevelAnnouncer::class)->flush();

        self::assertSame([], $this->changes);
    }

    /**
     * Another write followed the change before the transaction was over: what is told is the stock
     * before the change and the stock read back, never what the change alone wrote.
     */
    public function testAChangeThatWaitedIsToldUpToTheStockReadBack(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $announcer = $this->getService(StockLevelAnnouncer::class);
        $id = (int) $this->product?->getDefaultSaleElements()->getId();
        $connection->beginTransaction();

        try {
            $this->getService(PSEFacade::class)->decreaseStock($id, 3);
            $connection->exec('UPDATE product_sale_elements SET quantity = 17 WHERE id = '.$id);
        } finally {
            $connection->commit();
        }

        $announcer->flush();

        self::assertEquals([new StockLevelChange($id, 22.0, 17.0)], $this->changes);
    }

    /**
     * 22 -> 20 inside a transaction, then 20 -> 17 once it is committed: told once, from 22 to 17,
     * and the threshold of 18 is crossed once, not twice.
     */
    public function testAChangeAfterTheCommitTakesOverTheChangeThatWaitedForIt(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $announcer = $this->getService(StockLevelAnnouncer::class);
        $facade = $this->getService(PSEFacade::class);
        $saleElements = $this->product?->getDefaultSaleElements();
        $saleElements?->setLowStockThreshold(18)->save();
        $id = (int) $saleElements?->getId();
        $lowStockSignals = 0;
        $lowStockListener = static function () use (&$lowStockSignals): void {
            ++$lowStockSignals;
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, $lowStockListener);

        try {
            $connection->beginTransaction();

            try {
                $facade->decreaseStock($id, 2);
            } finally {
                $connection->commit();
            }

            $facade->decreaseStock($id, 3);
            $announcer->flush();
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, $lowStockListener);
        }

        self::assertEquals([new StockLevelChange($id, 22.0, 17.0)], $this->changes);
        self::assertSame(1, $lowStockSignals);
    }

    public function testTheEndOfTheRequestTheCommandAndTheMessageTellTheChangesThatWaited(): void
    {
        $events = StockLevelAnnouncer::getSubscribedEvents();

        foreach ([KernelEvents::TERMINATE, ConsoleEvents::TERMINATE, WorkerMessageHandledEvent::class, WorkerMessageFailedEvent::class] as $event) {
            self::assertSame('flush', $events[$event][0], $event);
        }
    }
}
