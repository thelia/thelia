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

namespace Thelia\Tests\Integration\Domain\Payment;

use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\CartQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * An order whose payment is authorized is not waiting for a payment: the checkout must
 * neither present it again to the module nor cancel it to place a new one, which would
 * reserve the amount a second time on the buyer's card.
 */
final class AuthorizedOrderCheckoutTest extends ActionIntegrationTestCase
{
    public function testAnOrderOnHoldForCaptureIsNotTheUnpaidOrderOfItsCart(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        $this->getService(PaymentTransactionRecorder::class)->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        $order = OrderQuery::create()->findPk($order->getId());
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $order->getOrderStatus()->getCode());

        $cart = CartQuery::create()->findPk($order->getCartId());

        self::assertNull($this->getService(OrderFacade::class)->findUnpaidOrderOf($cart));
    }

    public function testAnOrderWithoutAuthorizationIsStillTheUnpaidOrderOfItsCart(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        $cart = CartQuery::create()->findPk($order->getCartId());

        self::assertSame($order->getId(), $this->getService(OrderFacade::class)->findUnpaidOrderOf($cart)?->getId());
    }

    public function testTheModelSaysWhetherThePaymentOfAnOrderIsSecured(): void
    {
        $order = $this->factory->order(null, ['postage' => 120]);
        self::assertFalse($order->isPaymentSecured());

        $this->getService(PaymentTransactionRecorder::class)->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        self::assertTrue(OrderQuery::create()->findPk($order->getId())->isPaymentSecured());
    }
}
