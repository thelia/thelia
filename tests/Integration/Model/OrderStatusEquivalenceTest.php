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

use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\IntegrationTestCase;

final class OrderStatusEquivalenceTest extends IntegrationTestCase
{
    public function testCustomStatusEquivalentToPaidIsPaid(): void
    {
        $factory = $this->createFixtureFactory();
        $status = $factory->orderStatus([
            'code' => 'paid_on_delivery_test',
            'equivalentCode' => OrderStatus::CODE_PAID,
        ]);

        self::assertSame(OrderStatus::CODE_PAID, $status->getEffectiveCode());
        self::assertTrue($status->isPaid());
        self::assertTrue($status->isPaid(false));
        self::assertFalse($status->isNotPaid());

        $order = $factory->order(null, ['statusCode' => 'paid_on_delivery_test']);

        self::assertTrue($order->isPaid());
        self::assertFalse($order->isNotPaid());
    }

    public function testCustomStatusWithoutEquivalenceKeepsItsOwnCode(): void
    {
        $factory = $this->createFixtureFactory();
        $status = $factory->orderStatus(['code' => 'awaiting_stock_test']);

        self::assertNull($status->getEquivalentCode());
        self::assertSame('awaiting_stock_test', $status->getEffectiveCode());
        self::assertFalse($status->isPaid());
        self::assertFalse($status->isPaid(false));
        self::assertTrue($status->isNotPaid(false));

        $order = $factory->order(null, ['statusCode' => 'awaiting_stock_test']);

        self::assertFalse($order->isPaid());
    }

    public function testCustomStatusEquivalentToSentIsSentAndDerivedPaid(): void
    {
        $factory = $this->createFixtureFactory();
        $status = $factory->orderStatus([
            'code' => 'handed_to_carrier_test',
            'equivalentCode' => OrderStatus::CODE_SENT,
        ]);

        self::assertTrue($status->isSent());
        self::assertTrue($status->isPaid(false));
        self::assertFalse($status->isPaid());
    }

    public function testProtectedStatusIgnoresEquivalence(): void
    {
        $paid = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);

        self::assertNotNull($paid, 'Seeded order status "paid" is missing — run bin/test-prepare.');
        self::assertTrue($paid->getProtectedStatus());

        $paid->setEquivalentCode(OrderStatus::CODE_REFUNDED);

        self::assertSame(OrderStatus::CODE_PAID, $paid->getEffectiveCode());
        self::assertTrue($paid->isPaid());
        self::assertFalse($paid->isRefunded());
    }
}
