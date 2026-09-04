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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Test\IntegrationTestCase;

/**
 * A payment gateway sends the customer back to the shop with an order number and
 * nothing else. Whoever arrives that way may no longer be the one who left.
 *
 * A guest is a case this became reachable through: the checkout takes them out of the
 * session once the order is placed, so the return from the gateway can land with an
 * order number and an empty session.
 */
final class CheckoutPaymentCancelTest extends IntegrationTestCase
{
    private ?string $previousClientIp = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Presenting a tracking token spends a per-caller window shared by the whole
        // suite. Give this test its own caller address, so neither the rest of the suite
        // nor an earlier run is in the way of the cancellation it exercises.
        $request = $this->getService(RequestStack::class)->getMainRequest();
        $this->previousClientIp = $request?->server->get('REMOTE_ADDR');
        $request?->server->set('REMOTE_ADDR', '203.0.113.'.random_int(1, 254));
    }

    protected function tearDown(): void
    {
        $this->getService(RequestStack::class)
            ->getMainRequest()
            ?->server->set('REMOTE_ADDR', $this->previousClientIp);

        parent::tearDown();
    }

    public function testCancellingWithNobodyInTheSessionIsRefusedRatherThanFatal(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $this->session()->clearCustomerUser();

        $this->expectException(\InvalidArgumentException::class);

        $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId());
    }

    /**
     * A guest is taken out of the session the moment the order is placed, before the
     * payment module is even called, so the one thing they come back from a failed
     * payment holding is the tracking token issued for that order.
     */
    public function testAGuestCancelsWithTheTrackingTokenOfThatOrder(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));
        $token = $this->getService(GuestOrderAccessService::class)->createToken($order);

        $this->session()->clearCustomerUser();

        $cancelled = $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId(), $token);

        self::assertTrue($cancelled->isCancelled(), 'The order the token names must be taken back.');
    }

    /**
     * Anybody can order as a guest and get a token of their own, so a token that names
     * another order must cancel nothing.
     */
    public function testATrackingTokenForAnotherOrderCancelsNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $target = $factory->order($factory->guestCustomer($title));
        $ownOrder = $factory->order($factory->guestCustomer($title));
        $ownToken = $this->getService(GuestOrderAccessService::class)->createToken($ownOrder);

        $this->session()->clearCustomerUser();

        $this->expectException(\InvalidArgumentException::class);

        $this->getService(CheckoutPaymentService::class)->cancel((int) $target->getId(), $ownToken);
    }

    public function testAForgedTrackingTokenCancelsNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $this->session()->clearCustomerUser();

        $this->expectException(\InvalidArgumentException::class);

        $this->getService(CheckoutPaymentService::class)->cancel(
            (int) $order->getId(),
            $order->getId().'.'.(time() + 3600).'.'.str_repeat('a', 64),
        );
    }

    /**
     * "Cancel" here means "the payment did not go through". An order that was paid is
     * past that, and taking it back is a back-office decision with money behind it —
     * not something a tracking link may do, and a tracking link stays valid for a month
     * after the order was paid.
     *
     * @testWith ["paid"]
     *           ["processing"]
     *           ["sent"]
     *           ["canceled"]
     *           ["refunded"]
     */
    public function testAnOrderThatIsNoLongerWaitingForItsPaymentCannotBeCancelledWithItsToken(string $statusCode): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(
            $factory->guestCustomer($factory->customerTitle()),
            ['statusCode' => $statusCode],
        );
        $token = $this->getService(GuestOrderAccessService::class)->createToken($order);

        $this->session()->clearCustomerUser();

        $this->expectException(\InvalidArgumentException::class);

        $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId(), $token);
    }

    /**
     * The same guard for the customer the order belongs to: being the owner is not a
     * reason to cancel an order that has been paid.
     */
    public function testTheCustomerOfAPaidOrderCannotCancelItEither(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => 'paid']);

        $session = $this->session();
        $session->setCustomerUser($customer);

        try {
            $this->expectException(\InvalidArgumentException::class);

            $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId());
        } finally {
            $session->clearCustomerUser();
        }
    }

    public function testCancellingAnOrderOfSomeoneElseIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));
        $someoneElse = $factory->customer($factory->customerTitle());

        $session = $this->session();
        $session->setCustomerUser($someoneElse);

        try {
            $this->expectException(\InvalidArgumentException::class);

            $this->getService(CheckoutPaymentService::class)->cancel((int) $order->getId());
        } finally {
            $session->clearCustomerUser();
        }
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
