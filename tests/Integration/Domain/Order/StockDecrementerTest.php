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

    /**
     * The column is a single precision FLOAT, which keeps 0.7 as 0.69999998: compared as it
     * is, the last 0.7 kg in stock would be refused to a buyer of 0.7 kg.
     */
    public function testTheLastWeightInStockCanBeSold(): void
    {
        $productSaleElements = $this->createProductSaleElements(0.7);

        $this->decrement($productSaleElements, 0.7, guardAvailability: true, allowNegativeStock: false);

        self::assertSame(0, $this->reloadQuantityInThousandths($productSaleElements));
    }

    /**
     * Each decrement of a FLOAT keeps the error of the one before it: ten orders of 10 g
     * taken from 10 tonnes would leave 9999.902 kg in the column. The stock is compared
     * on the thousandth, the finest decimal a quantity is sold with.
     */
    public function testTenWeighedOrdersLeaveTheStockWithoutDrift(): void
    {
        $productSaleElements = $this->createProductSaleElements(10000);

        for ($order = 0; $order < 10; ++$order) {
            $this->decrement($productSaleElements, 0.01, guardAvailability: true, allowNegativeStock: false);
        }

        self::assertSame(9_999_900, $this->reloadQuantityInThousandths($productSaleElements));

        for ($cancellation = 0; $cancellation < 10; ++$cancellation) {
            $this->stockDecrementer->increment($productSaleElements->getId(), 0.01, $this->getPropelConnection());
        }

        self::assertSame(10_000_000, $this->reloadQuantityInThousandths($productSaleElements));
    }

    private function createProductSaleElements(int|float $quantity): ProductSaleElements
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

    /**
     * The stock as the column holds it, in thousandths. Read through the model, a FLOAT
     * comes back with six significant digits, which hides the drift of the column.
     */
    private function reloadQuantityInThousandths(ProductSaleElements $productSaleElements): int
    {
        $statement = $this->getPropelConnection()->prepare('SELECT ROUND(`quantity` * 1000) FROM `product_sale_elements` WHERE `id` = :id');
        $statement->bindValue(':id', $productSaleElements->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function reloadQuantity(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->findPk($productSaleElements->getId())
            ->getQuantity();
    }
}
