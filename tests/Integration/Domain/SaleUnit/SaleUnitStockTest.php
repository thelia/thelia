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

use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * How a sale unit's own stock is decremented and checked: its own row moves and
 * the shared national counter is left untouched when the unit keeps one; the
 * national counter moves, exactly as before the feature, when it does not.
 */
final class SaleUnitStockTest extends IntegrationTestCase
{
    private StockDecrementer $stockDecrementer;

    private SaleUnitStockResolver $stockResolver;

    private FixtureFactory $fixtures;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockDecrementer = $this->getService(StockDecrementer::class);
        $this->stockResolver = $this->getService(SaleUnitStockResolver::class);

        $this->fixtures = $this->createFixtureFactory();
        $currency = $this->fixtures->currency();
        $product = $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $currency);
        $this->productSaleElements = $product->getDefaultSaleElements();
        $this->productSaleElements->setQuantity(100)->save();
        $this->saleUnit = $this->fixtures->saleUnit();
    }

    public function testTheUnitStockIsDecrementedAndTheNationalCounterUntouched(): void
    {
        $this->fixtures->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 10.0);

        $this->stockDecrementer->decrement(
            $this->productSaleElements->getId(),
            3.0,
            guardAvailability: true,
            allowNegativeStock: false,
            connection: $this->getPropelConnection(),
            saleUnitId: $this->saleUnit->getId(),
        );

        self::assertEqualsWithDelta(7.0, $this->unitStockOf(), 0.0001);

        $this->productSaleElements->reload();
        self::assertEqualsWithDelta(100.0, (float) $this->productSaleElements->getQuantity(), 0.0001, 'the national counter is left untouched when the unit keeps its own');
    }

    public function testWithoutAUnitRowTheNationalCounterIsDecremented(): void
    {
        $this->stockDecrementer->decrement(
            $this->productSaleElements->getId(),
            4.0,
            guardAvailability: true,
            allowNegativeStock: false,
            connection: $this->getPropelConnection(),
            saleUnitId: $this->saleUnit->getId(),
        );

        $this->productSaleElements->reload();
        self::assertEqualsWithDelta(96.0, (float) $this->productSaleElements->getQuantity(), 0.0001, 'no unit row: the shared national counter moves, the fallback the feature stands on');
    }

    public function testTheGuardRefusesToOversellAUnitStock(): void
    {
        $this->fixtures->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 2.0);

        try {
            $this->stockDecrementer->decrement(
                $this->productSaleElements->getId(),
                5.0,
                guardAvailability: true,
                allowNegativeStock: false,
                connection: $this->getPropelConnection(),
                saleUnitId: $this->saleUnit->getId(),
            );
            self::fail('overselling a unit stock must abort');
        } catch (\Thelia\Exception\TheliaProcessException) {
            // expected
        }

        self::assertEqualsWithDelta(2.0, $this->unitStockOf(), 0.0001, 'the refused decrement left the unit stock as it was');
    }

    public function testAvailableQuantityReadsTheUnitRowWhenItExists(): void
    {
        $this->fixtures->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 8.0);

        self::assertEqualsWithDelta(
            8.0,
            $this->stockResolver->availableQuantity($this->saleUnit->getId(), $this->productSaleElements->getId(), 100.0),
            0.0001,
        );

        self::assertEqualsWithDelta(
            100.0,
            $this->stockResolver->availableQuantity(null, $this->productSaleElements->getId(), 100.0),
            0.0001,
            'no unit in context: the national quantity is what is available',
        );
    }

    private function unitStockOf(): float
    {
        $row = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId($this->saleUnit->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->findOne();

        return null === $row ? 0.0 : (float) $row->getQuantity();
    }
}
