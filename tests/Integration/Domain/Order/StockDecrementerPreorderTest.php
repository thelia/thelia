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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\Service\StockMovement;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What the sale mode of a sale element at zero stock does to the conditional decrement,
 * and what the decrement reports of the stock it moved.
 */
final class StockDecrementerPreorderTest extends ActionIntegrationTestCase
{
    private StockDecrementer $stockDecrementer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockDecrementer = $this->getService(StockDecrementer::class);
    }

    public function testTheModeThatRefusesStaysAsItWas(): void
    {
        $saleElements = $this->saleElements(3, OutOfStockPolicy::Refuse, 10.0);

        try {
            $this->decrement($saleElements, 5.0);
            self::fail('Five out of three is refused in the mode that refuses.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(3.0, $this->quantityOf($saleElements));
    }

    public function testALimitedPreorderOfTenSellsTenFromZeroAndRefusesTheEleventh(): void
    {
        $saleElements = $this->saleElements(0, OutOfStockPolicy::Limited, 10.0);

        $this->decrement($saleElements, 10.0);

        self::assertSame(-10.0, $this->quantityOf($saleElements));

        $this->expectException(TheliaProcessException::class);
        $this->expectExceptionMessage('Not enough stock');

        $this->decrement($saleElements, 1.0);
    }

    public function testALimitedPreorderIsRefusedWhenOneUnitGoesBeyondTheLimit(): void
    {
        $saleElements = $this->saleElements(0, OutOfStockPolicy::Limited, 10.0);

        try {
            $this->decrement($saleElements, 11.0);
            self::fail('Eleven units on a limit of ten are refused.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(0.0, $this->quantityOf($saleElements), 'A refusal leaves the stock untouched.');
    }

    public function testThreeInStockWithALimitOfTenSellsFiveAndLeavesMinusTwoMarkedAsAPreorder(): void
    {
        $saleElements = $this->saleElements(3, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        $movement = $this->decrement($saleElements, 5.0);

        self::assertSame(-2.0, $this->quantityOf($saleElements));
        self::assertInstanceOf(StockMovement::class, $movement);
        self::assertSame(3.0, $movement->previousQuantity);
        self::assertSame(-2.0, $movement->newQuantity);
        self::assertTrue($movement->preorder);
        self::assertSame('2027-02-01', $movement->restockDate);
    }

    public function testAnUnlimitedModeSellsWhateverTheStock(): void
    {
        $saleElements = $this->saleElements(1, OutOfStockPolicy::Unlimited, null);

        $movement = $this->decrement($saleElements, 100.0);

        self::assertSame(-99.0, $this->quantityOf($saleElements));
        self::assertTrue($movement?->preorder);
    }

    public function testAnOrderTheStockCoversIsNotAPreorderWhateverTheMode(): void
    {
        $saleElements = $this->saleElements(10, OutOfStockPolicy::Limited, 10.0, '2027-02-01');

        $movement = $this->decrement($saleElements, 4.0);

        self::assertSame(10.0, $movement?->previousQuantity);
        self::assertSame(6.0, $movement?->newQuantity);
        self::assertFalse($movement?->preorder);
        self::assertNull($movement?->restockDate);
    }

    public function testASaleElementThatNeverSellsBelowZeroIsNeverAPreorderEvenWhenTheShopAllowsNegativeStock(): void
    {
        $saleElements = $this->saleElements(3, OutOfStockPolicy::Refuse, null);

        $movement = $this->decrement($saleElements, 5.0, allowNegativeStock: true);

        self::assertSame(-2.0, $this->quantityOf($saleElements), 'allow_negative_stock keeps its meaning.');
        self::assertFalse($movement?->preorder);
    }

    public function testAShopThatDoesNotCheckTheStockKeepsTheHistoricalFloorAndMarksNoPreorder(): void
    {
        $saleElements = $this->saleElements(3, OutOfStockPolicy::Limited, 10.0);

        $movement = $this->decrement($saleElements, 5.0, guardAvailability: false);

        self::assertSame(0.0, $this->quantityOf($saleElements));
        self::assertSame(3.0, $movement?->previousQuantity);
        self::assertSame(0.0, $movement?->newQuantity);
        self::assertFalse($movement?->preorder);
    }

    public function testAMovementOfNothingReportsNothing(): void
    {
        $saleElements = $this->saleElements(3, OutOfStockPolicy::Refuse, null);

        self::assertNull($this->decrement($saleElements, 0.0));
    }

    public function testTheOwnStockOfASaleUnitIsJudgedByTheModeOfTheSaleElement(): void
    {
        $saleElements = $this->saleElements(100, OutOfStockPolicy::Limited, 4.0);
        $saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($saleUnit, $saleElements, 1.0);

        $movement = $this->stockDecrementer->decrement($saleElements->getId(), 5.0, true, false, $this->getPropelConnection(), $saleUnit->getId());

        self::assertSame(100.0, $this->quantityOf($saleElements), 'The shared counter is left alone.');
        self::assertSame($saleUnit->getId(), $movement?->saleUnitId);
        self::assertTrue($movement?->onSaleUnitStock());
        self::assertSame(1.0, $movement->previousQuantity);
        self::assertSame(-4.0, $movement->newQuantity);
        self::assertTrue($movement->preorder);

        $this->expectException(TheliaProcessException::class);
        $this->stockDecrementer->decrement($saleElements->getId(), 1.0, true, false, $this->getPropelConnection(), $saleUnit->getId());
    }

    private function saleElements(int $quantity, OutOfStockPolicy $policy, ?float $limit, ?string $restockDate = null): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $saleElements = $this->factory->productSaleElement($product, ['quantity' => $quantity]);
        $saleElements
            ->setOutOfStockPolicy($policy->value)
            ->setPreorderLimit($limit)
            ->setRestockDate($restockDate)
            ->save($this->getPropelConnection());

        return $saleElements;
    }

    private function decrement(ProductSaleElements $saleElements, float $quantity, bool $guardAvailability = true, bool $allowNegativeStock = false): ?StockMovement
    {
        return $this->stockDecrementer->decrement(
            $saleElements->getId(),
            $quantity,
            $guardAvailability,
            $allowNegativeStock,
            $this->getPropelConnection(),
        );
    }

    private function quantityOf(ProductSaleElements $saleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($saleElements->getId())->getQuantity();
    }
}
