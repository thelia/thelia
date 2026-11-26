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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockWriter;
use Thelia\Model\Map\SaleUnitProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitProductSaleElements;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The stock and the threshold a sale unit keeps of a sale element, written by an editor: a change of
 * the quantity is told on the event of the stock of the sale units, a row created or removed is not.
 */
final class SaleUnitStockWriterTest extends ActionIntegrationTestCase
{
    /** @var list<StockLevelChange> */
    private array $told = [];

    private ?\Closure $listener = null;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->productSaleElements = $this->factory->productSaleElement($product, ['quantity' => 50.0]);
        $this->saleUnit = $this->factory->saleUnit();

        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
        $this->told = [];
        $this->listener = function (SaleUnitStockChangedEvent $event): void {
            $this->told = [...$this->told, ...$event->getChanges()];
        };
        $this->dispatcher->addListener(TheliaEvents::SALE_UNIT_STOCK_CHANGED, $this->listener);
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->dispatcher->removeListener(TheliaEvents::SALE_UNIT_STOCK_CHANGED, $this->listener);
        }
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAChangeOfTheQuantityIsWrittenWithItsThresholdAndTold(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 10.0);

        self::assertTrue($this->writer()->write($this->saleUnit->getId(), $this->productSaleElements->getId(), 4.5, 3.0));

        $row = $this->row();
        self::assertSame('4.500000', $row?->getQuantity());
        self::assertSame(3.0, $row?->getLowStockThreshold());
        self::assertEquals([new StockLevelChange($this->productSaleElements->getId(), 10.0, 4.5, $this->saleUnit->getId())], $this->told);
    }

    public function testARowCreatedOrRemovedIsNotAMovementOfTheStock(): void
    {
        self::assertTrue($this->writer()->write($this->saleUnit->getId(), $this->productSaleElements->getId(), 6.0));
        self::assertSame('6.000000', $this->row()?->getQuantity());
        self::assertNull($this->row()?->getLowStockThreshold());

        self::assertTrue($this->writer()->write($this->saleUnit->getId(), $this->productSaleElements->getId(), null));
        self::assertNull($this->row());

        self::assertFalse($this->writer()->write($this->saleUnit->getId(), $this->productSaleElements->getId(), null), 'Nothing to remove.');
        self::assertSame([], $this->told);
    }

    public function testANegativeThresholdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->writer()->write($this->saleUnit->getId(), $this->productSaleElements->getId(), 6.0, -1.0);
    }

    private function writer(): SaleUnitStockWriter
    {
        return $this->getService(SaleUnitStockWriter::class);
    }

    private function row(): ?SaleUnitProductSaleElements
    {
        SaleUnitProductSaleElementsTableMap::clearInstancePool();

        return SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->findOne($this->getPropelConnection());
    }
}
