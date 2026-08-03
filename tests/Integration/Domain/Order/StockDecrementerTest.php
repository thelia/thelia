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

use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class StockDecrementerTest extends ActionIntegrationTestCase
{
    private StockDecrementer $stockDecrementer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockDecrementer = $this->getService(StockDecrementer::class);
    }

    public function testGuardedDecrementConsumesAvailableStock(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);

        $this->decrement($productSaleElements, 4.0, guardAvailability: true, allowNegativeStock: false);

        self::assertSame(6.0, $this->reloadQuantity($productSaleElements));
    }

    public function testGuardedDecrementRefusesInsufficientStock(): void
    {
        $productSaleElements = $this->createProductSaleElements(3);

        $this->expectException(TheliaProcessException::class);
        $this->expectExceptionMessage('Not enough stock');

        $this->decrement($productSaleElements, 5.0, guardAvailability: true, allowNegativeStock: false);
    }

    public function testGuardedDecrementLeavesStockUntouchedOnRefusal(): void
    {
        $productSaleElements = $this->createProductSaleElements(3);

        try {
            $this->decrement($productSaleElements, 5.0, guardAvailability: true, allowNegativeStock: false);
        } catch (TheliaProcessException) {
        }

        self::assertSame(3.0, $this->reloadQuantity($productSaleElements));
    }

    public function testUncheckedDecrementClampsAtZeroLikeTheHistoricalBehaviour(): void
    {
        $productSaleElements = $this->createProductSaleElements(3);

        $this->decrement($productSaleElements, 5.0, guardAvailability: false, allowNegativeStock: false);

        self::assertSame(0.0, $this->reloadQuantity($productSaleElements));
    }

    public function testNegativeStockGoesBelowZeroWhenAllowed(): void
    {
        $productSaleElements = $this->createProductSaleElements(3);

        $this->decrement($productSaleElements, 5.0, guardAvailability: true, allowNegativeStock: true);

        self::assertSame(-2.0, $this->reloadQuantity($productSaleElements));
    }

    public function testZeroQuantityIsANoOpAndNotARefusal(): void
    {
        $productSaleElements = $this->createProductSaleElements(3);

        $this->decrement($productSaleElements, 0.0, guardAvailability: true, allowNegativeStock: false);

        self::assertSame(3.0, $this->reloadQuantity($productSaleElements));
    }

    private function createProductSaleElements(int $quantity): ProductSaleElements
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
        );

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    private function decrement(
        ProductSaleElements $productSaleElements,
        float $quantity,
        bool $guardAvailability,
        bool $allowNegativeStock,
    ): void {
        $this->stockDecrementer->decrement(
            $productSaleElements->getId(),
            $quantity,
            $guardAvailability,
            $allowNegativeStock,
            $this->getPropelConnection(),
        );
    }

    private function reloadQuantity(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->findPk($productSaleElements->getId())
            ->getQuantity();
    }
}
