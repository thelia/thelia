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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The totals of an order are read once per order instance, and go away with it: an export
 * or a worker that reads the totals of thousands of orders does not keep one row per order
 * until the process ends.
 */
final class OrderTotalsCacheTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private const ORDERS_READ = 5000;

    public function testTheTotalsOfOrdersNoLongerReadAreNotKept(): void
    {
        $this->readTotalsOfOrdersStartingAt(1_000_000, 50);
        gc_collect_cycles();
        $before = memory_get_usage();

        $this->readTotalsOfOrdersStartingAt(2_000_000, self::ORDERS_READ);
        gc_collect_cycles();

        self::assertLessThan(
            256 * 1024,
            memory_get_usage() - $before,
            \sprintf('Reading the totals of %d orders must not keep memory once the orders are gone.', self::ORDERS_READ),
        );
    }

    public function testAnOrderReadsItsTotalsOnce(): void
    {
        $order = $this->orderWithALine('10.00');

        $statements = $this->recordSqlQueries(static function () use ($order): void {
            $tax = 0;
            $order->getTotalAmount();
            $order->getTotalAmount($tax, false, false);
        });

        self::assertSame(1, $this->countTotalsQueries($statements));
    }

    public function testForgettingTheTotalsOfAnOrderMakesEveryInstanceReadThemAgain(): void
    {
        $order = $this->orderWithALine('10.00');
        OrderTableMap::clearInstancePool();
        $otherInstance = OrderQuery::create()->findPk($order->getId());
        self::assertNotSame($order, $otherInstance);
        $before = $order->getTotalAmount();
        self::assertSame($before, $otherInstance->getTotalAmount());

        $this->addLine((int) $order->getId(), '5.00');
        Order::forgetCachedTotals((int) $order->getId());

        self::assertEqualsWithDelta($before + 5.0, $order->getTotalAmount(), 0.001);
        self::assertEqualsWithDelta($before + 5.0, $otherInstance->getTotalAmount(), 0.001);
    }

    public function testAReloadedOrderReadsItsTotalsAgain(): void
    {
        $order = $this->orderWithALine('10.00');
        $before = $order->getTotalAmount();

        $this->addLine((int) $order->getId(), '5.00');
        $order->reload();

        self::assertEqualsWithDelta($before + 5.0, $order->getTotalAmount(), 0.001);
    }

    public function testASerializedOrderDoesNotCarryItsTotals(): void
    {
        $order = $this->orderWithALine('10.00');
        $before = $order->getTotalAmount();

        $this->addLine((int) $order->getId(), '5.00');
        $restored = unserialize(serialize($order));

        self::assertEqualsWithDelta($before + 5.0, $restored->getTotalAmount(), 0.001);
    }

    private function readTotalsOfOrdersStartingAt(int $firstId, int $count): void
    {
        for ($id = $firstId; $id < $firstId + $count; ++$id) {
            (new Order())->setId($id)->getTotalAmount();
        }
    }

    private function orderWithALine(string $price): Order
    {
        $order = $this->createFixtureFactory()->order();
        $this->addLine((int) $order->getId(), $price);

        return $order;
    }

    private function addLine(int $orderId, string $price): void
    {
        $line = (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef('REF')
            ->setProductSaleElementsRef('PSE')
            ->setProductSaleElementsId(0)
            ->setTitle('Line')
            ->setQuantity(1)
            ->setPrice($price)
            ->setPromoPrice($price)
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save();
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('0')->setPromoAmount('0')->save();
    }

    /**
     * @param list<string> $statements
     */
    private function countTotalsQueries(array $statements): int
    {
        return \count(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'total_taxed_price')));
    }
}
