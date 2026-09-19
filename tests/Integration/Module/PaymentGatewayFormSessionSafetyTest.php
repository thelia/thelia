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

namespace Thelia\Tests\Integration\Module;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Test\IntegrationTestCase;

/**
 * The gateway form a payment module renders used to be reachable only from a browser.
 *
 * It counts the items of the cart, and it went and got them from the session — on a
 * request that has none, which is the shape of a stateless API request, that is not a
 * count of zero but a SessionNotFoundException thrown in the middle of a payment, after
 * the order was written. A module rendering its gateway form is now answerable wherever
 * the order was placed from.
 */
final class PaymentGatewayFormSessionSafetyTest extends IntegrationTestCase
{
    public function testAModuleWithNoSessionBehindItStillCountsTheCart(): void
    {
        $module = new class extends AbstractPaymentModule {
            public function pay(Order $order): null
            {
                return null;
            }

            public function isValidPayment(): bool
            {
                return true;
            }

            public function exposedCartItemCount(): int
            {
                return $this->cartItemCount();
            }
        };

        $module->setContainer(static::getContainer());
        // A request with no session at all: what a stateless API request looks like.
        $module->setRequest(Request::create('http://localhost/api/front/checkout'));

        self::assertSame(0, $module->exposedCartItemCount());
    }

    public function testAModuleWithASessionBehindItStillCountsWhatTheSessionHolds(): void
    {
        $module = new class extends AbstractPaymentModule {
            public function pay(Order $order): null
            {
                return null;
            }

            public function isValidPayment(): bool
            {
                return true;
            }

            public function exposedCartItemCount(): int
            {
                return $this->cartItemCount();
            }
        };

        $module->setContainer(static::getContainer());

        $request = $this->getService(RequestStack::class)->getMainRequest();
        self::assertInstanceOf(Request::class, $request);
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $factory->cartItem($cart, $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['baseQuantity' => 10],
        ));
        $session->setSessionCart($cart);

        $module->setRequest($request);

        try {
            self::assertSame(1, $module->exposedCartItemCount());
        } finally {
            $session->setSessionCart(null);
        }
    }
}
