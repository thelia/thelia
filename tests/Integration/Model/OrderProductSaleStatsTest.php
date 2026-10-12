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

use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * {@see OrderProductQuery::getSaleStats()} counts the units of a product sold in paid
 * orders, between two days when they are given: from the start of the first one to the
 * end of the last one.
 */
final class OrderProductSaleStatsTest extends ActionIntegrationTestCase
{
    private string $productRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRef = 'MUG-'.uniqid();

        $this->soldOn('2026-03-10 10:00:00', 2.0, OrderStatus::CODE_PAID);
        $this->soldOn('2026-03-20 18:30:00', 3.0, OrderStatus::CODE_PAID);
        $this->soldOn('2026-03-15 09:00:00', 7.0, OrderStatus::CODE_NOT_PAID);
    }

    public function testWithoutDatesEveryPaidUnitCounts(): void
    {
        self::assertSame(5.0, (float) OrderProductQuery::getSaleStats($this->productRef));
    }

    public function testBothDaysBoundTheCount(): void
    {
        self::assertSame(2.0, (float) OrderProductQuery::getSaleStats(
            $this->productRef,
            new \DateTime('2026-03-01'),
            new \DateTime('2026-03-10'),
        ));
    }

    public function testAStartDayAloneCountsFromThatDayOn(): void
    {
        self::assertSame(3.0, (float) OrderProductQuery::getSaleStats($this->productRef, new \DateTime('2026-03-11')));
    }

    public function testAnEndDayAloneCountsUpToTheEndOfThatDay(): void
    {
        self::assertSame(2.0, (float) OrderProductQuery::getSaleStats($this->productRef, null, new \DateTime('2026-03-10')));
    }

    private function soldOn(string $createdAt, float $quantity, string $statusCode): void
    {
        $order = $this->factory->order(null, ['statusCode' => $statusCode]);
        $order->setCreatedAt($createdAt)->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($this->productRef)
            ->setProductSaleElementsRef($this->productRef)
            ->setTitle('Mug')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());
    }
}
