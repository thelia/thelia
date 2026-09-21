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
use Thelia\Test\IntegrationTestCase;

/**
 * OrderFacade::liveOrderOf() excludes the cancelled orders of a cart by filtering on
 * order_status.code, so it only ever excludes the protected CANCELED row. A shop's own
 * status standing for canceled — equivalent_code = canceled, which is exactly what
 * AC2 asks to be honoured on the paid side through isPaid(false) — is still counted as
 * a live order.
 *
 * findUnpaidOrderOf() then hands that cancelled order back as the order waiting for its
 * payment: a new attempt presents a cancelled order to the payment module, or tries to
 * cancel it a second time.
 */
final class CancelledEquivalentStatusIsNotLiveTest extends IntegrationTestCase
{
    public function testAnOrderInAStatusEquivalentToCancelledIsNotTheUnpaidOrderOfItsCart(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();

        $cart = CartQuery::create()->findPk($order->getCartId(), $this->getPropelConnection());
        self::assertNotNull($cart, 'The fixture order names a cart.');

        $refusedByTheBank = $factory->orderStatus([
            'code' => 'refused-by-the-bank-'.uniqid(),
            'equivalentCode' => OrderStatus::CODE_CANCELED,
        ]);
        self::assertSame(
            OrderStatus::CODE_CANCELED,
            $refusedByTheBank->getEffectiveCode(),
            'The shop status stands for the canonical cancelled code.',
        );

        $order->setStatusId((int) $refusedByTheBank->getId())->save($this->getPropelConnection());

        self::assertNull(
            $this->getService(OrderFacade::class)->findUnpaidOrderOf($cart),
            'An order in a status the shop declared equivalent to cancelled is cancelled: it must not be handed back as the order still waiting for its payment.',
        );
    }
}
