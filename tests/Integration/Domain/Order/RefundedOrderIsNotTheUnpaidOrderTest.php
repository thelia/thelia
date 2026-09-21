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

use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * OrderFacade::findUnpaidOrderOf() decides whether a new payment attempt reuses or
 * cancels the order that already stands on the cart. It reads "unpaid" as
 * isNotPaid(false), which is "not paid, not processing, not sent" — and a REFUNDED
 * order answers true to that. A refunded order is handed back as the order waiting
 * for its payment, so a second attempt either presents it to the payment module
 * again (payAgain) or writes CANCELED over the refund (setCancelled).
 *
 * Session::hasBeenPaidFor() reads the same order the other way round — it counts
 * refunded as done and consumes the cart — so the two halves of this work disagree.
 */
final class RefundedOrderIsNotTheUnpaidOrderTest extends IntegrationTestCase
{
    public function testARefundedOrderIsNotTheUnpaidOrderOfItsCart(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $cart = CartQuery::create()->findPk($order->getCartId(), $this->getPropelConnection());
        self::assertNotNull($cart, 'The fixture order names a cart.');

        $refunded = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_REFUNDED, $this->getPropelConnection());
        self::assertNotNull($refunded, 'The refunded status is seeded.');

        $order->setStatusId((int) $refunded->getId())->save($this->getPropelConnection());

        self::assertNull(
            $this->getService(OrderFacade::class)->findUnpaidOrderOf($cart),
            'A refunded order is no longer waiting for its payment: it must not be handed back as the unpaid order of the cart, or a new attempt would pay it again or cancel the refund.',
        );
    }
}
