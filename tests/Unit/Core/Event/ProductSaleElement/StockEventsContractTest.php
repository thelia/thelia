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

namespace Thelia\Tests\Unit\Core\Event\ProductSaleElement;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;

/** The contract other code listens against: names, arguments and getters must not move. */
final class StockEventsContractTest extends TestCase
{
    public function testTheEventNamesAreFrozen(): void
    {
        self::assertSame('thelia.pse.stock_changed', TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED);
        self::assertSame('thelia.pse.low_stock_reached', TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED);
        self::assertSame('thelia.sale_unit.stock_changed', TheliaEvents::SALE_UNIT_STOCK_CHANGED);
    }

    public function testAStockChangeCarriesTheLevelsBeforeAndAfter(): void
    {
        $change = new StockLevelChange(productSaleElementsId: 42, previousQuantity: 22.0, newQuantity: 19.0);
        $event = new StockLevelChangedEvent(changes: [$change]);

        self::assertSame([$change], $event->getChanges());
        self::assertSame(42, $event->getChanges()[0]->productSaleElementsId);
        self::assertSame(22.0, $event->getChanges()[0]->previousQuantity);
        self::assertSame(19.0, $event->getChanges()[0]->newQuantity);
        self::assertNull($event->getChanges()[0]->saleUnitId, 'A change built as before is a change of the national counter.');
    }

    public function testAChangeOfTheStockOfASaleUnitNamesTheUnit(): void
    {
        $change = new StockLevelChange(productSaleElementsId: 42, previousQuantity: 10.0, newQuantity: 4.0, saleUnitId: 3);
        $event = new SaleUnitStockChangedEvent(changes: [$change]);

        self::assertSame([$change], $event->getChanges());
        self::assertSame(3, $event->getChanges()[0]->saleUnitId);
        self::assertTrue($change->onSaleUnitStock());
    }

    public function testTheLowStockEventCarriesTheQuantityAndTheThreshold(): void
    {
        $event = new LowStockReachedEvent(productSaleElementsId: 42, quantity: 19.0, threshold: 20.0);

        self::assertSame(42, $event->getProductSaleElementsId());
        self::assertSame(19.0, $event->getQuantity());
        self::assertSame(20.0, $event->getThreshold());
        self::assertNull($event->getSaleUnitId());
        self::assertSame(3, (new LowStockReachedEvent(productSaleElementsId: 42, quantity: 4.0, threshold: 5.0, saleUnitId: 3))->getSaleUnitId());
    }
}
