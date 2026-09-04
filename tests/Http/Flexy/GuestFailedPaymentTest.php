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

namespace Thelia\Tests\Http\Flexy;

use FlexyBundle\Controller\CheckoutController;
use FlexyBundle\Service\GuestOrderTracking;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessLimiter;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * A guest coming back from a failed payment has no session customer to check the
 * failure page against — the core retires them from the session the moment the order
 * exists. What is left to prove the order is theirs is the tracking token this session
 * was handed when it was placed, which CheckoutController::failedAction() must read and
 * hand to the core cancellation the same way it already does for a signed-in customer.
 *
 * Driven at the controller level with the real kernel rather than over HTTP: the guest
 * order token this reaches for lives in the session under a key GuestOrderTracking
 * reads off RequestStack::getMainRequest() — not the request a functional test client
 * gets back, and not one this test could push either, since getMainRequest() answers
 * the bottom of the stack. The request IntegrationTestCase pushes there at setUp() is
 * the only one this reaches, so that is the one this mutates directly.
 */
final class GuestFailedPaymentTest extends IntegrationTestCase
{
    /**
     * The per-order window of a tracking link is ten calls. Spending nine leaves room
     * for exactly one, which is what the failure page is entitled to.
     */
    private const CALLS_LEAVING_ROOM_FOR_ONE_MORE = 9;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever theme version
        // it is given, and a theme that predates the guest checkout has no
        // GuestOrderTracking to drive this scenario through.
        if (!class_exists(GuestOrderTracking::class)) {
            self::markTestSkipped('The installed theme predates the guest checkout.');
        }

        parent::setUp();
    }

    public function testAGuestsOrderIsCancelledWhenThePaymentComesBackFailed(): void
    {
        $order = $this->guestOrder();

        $this->callFailedAction($order->getId(), $this->tokenFor($order));

        $this->forgetHydratedOrders();
        $afterFailure = OrderQuery::create()->findPk($order->getId());

        self::assertNotNull($afterFailure);
        self::assertTrue(
            $afterFailure->isCancelled(),
            'An order still waiting for a payment that came back failed must be cancelled, guest or not.',
        );
    }

    public function testATokenNamingAnotherOrderDoesNotCancelThisOne(): void
    {
        $order = $this->guestOrder();
        $someoneElsesOrder = $this->guestOrder();

        $this->callFailedAction($order->getId(), $this->tokenFor($someoneElsesOrder));

        $this->forgetHydratedOrders();
        $stillWaiting = OrderQuery::create()->findPk($order->getId());

        self::assertNotNull($stillWaiting);
        self::assertFalse(
            $stillWaiting->isCancelled(),
            "A token naming someone else's order must not be enough to cancel this one.",
        );
    }

    /**
     * The failure page spent the tracking link's budget twice on one load — once
     * resolving the order, once inside the cancellation — so the second call could be
     * refused while the first was allowed. The refusal came out of the controller as an
     * exception, and the buyer was shown a server error instead of being told their
     * payment had failed.
     */
    public function testTheFailurePageIsAnsweredEvenWhenTheTrackingBudgetIsNearlySpent(): void
    {
        $order = $this->guestOrder();
        $token = $this->tokenFor($order);

        $this->comeFromAClientOfThisRunsOwn((int) $order->getId());
        $this->spendTrackingBudget($token, self::CALLS_LEAVING_ROOM_FOR_ONE_MORE);

        $response = $this->callFailedAction($order->getId(), $token);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Telling the buyer their payment failed must not depend on a rate limit.',
        );

        $this->forgetHydratedOrders();

        self::assertTrue(
            OrderQuery::create()->findPk($order->getId())?->isCancelled(),
            'And one call is all the cancellation needs, so the order is still taken back.',
        );
    }

    /**
     * An order the core refuses to cancel — already taken back, or paid for by a
     * confirmation that crossed the failure return — leaves this page a failure page.
     */
    public function testAnOrderTheCoreWillNotCancelStillGetsAFailurePage(): void
    {
        $order = $this->guestOrder();
        $token = $this->tokenFor($order);

        $order->setCancelled();

        $response = $this->callFailedAction($order->getId(), $token);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A gateway returning twice, or a page refreshed, is not a server error.',
        );
    }

    /**
     * Puts this test on a client address nothing else has used.
     *
     * The tracking budget is counted per order and per client, and the per-client window
     * is shared by every test in this process — and outlives the process, so a second run
     * inside the same quarter of an hour would inherit what the first one spent. Spending
     * the per-order budget down to a single call, which is what the test below is about,
     * only means anything if the per-client one is not the thing running out instead.
     *
     * Derived from the order the run has just created, in the documentation range: ids
     * never come back, so neither does the address.
     */
    private function comeFromAClientOfThisRunsOwn(int $orderId): void
    {
        static::getContainer()
            ->get(RequestStack::class)
            ->getMainRequest()
            ?->server->set('REMOTE_ADDR', \sprintf('203.0.%d.%d', intdiv($orderId, 254) % 254, $orderId % 254))
        ;
    }

    /**
     * Consumes the tracking budget of that token, straight through the limiter the
     * controller goes through, without touching the page under test.
     */
    private function spendTrackingBudget(string $token, int $calls): void
    {
        $limiter = $this->getService(GuestOrderAccessLimiter::class);

        for ($call = 0; $call < $calls; ++$call) {
            $limiter->allows($token);
        }
    }

    /**
     * Reproduces exactly what a guest's browser hands the controller on a failure
     * return: the order named by id, and a session — no customer in it — that still
     * carries the tracking token from when the order was placed.
     */
    private function callFailedAction(?int $orderId, string $sessionGuestOrderToken): Response
    {
        $container = static::getContainer();
        $request = $container->get(RequestStack::class)->getMainRequest();

        self::assertInstanceOf(
            Request::class,
            $request,
            'IntegrationTestCase must have pushed the request GuestOrderTracking and SecurityContext read from.',
        );
        self::assertTrue($request->hasSession());

        $request->query->set('order_id', $orderId);
        $request->getSession()->set('flexy.guest_order_token', $sessionGuestOrderToken);

        return $container->get(CheckoutController::class)->failedAction(
            $container->get(CheckoutFacade::class),
            $request,
            $container->get(GuestOrderTracking::class),
        );
    }

    private function tokenFor(Order $order): string
    {
        return $this->getService(GuestOrderAccessService::class)->createToken($order);
    }

    private function guestOrder(): Order
    {
        $factory = $this->createFixtureFactory();

        return $factory->order(
            $factory->guestCustomer($factory->customerTitle()),
            ['statusCode' => OrderStatus::CODE_NOT_PAID],
        );
    }

    /**
     * The single kernel this suite shares across tests keeps hydrated models in the
     * Propel instance pool, which would otherwise hand the assertions below the same
     * in-memory Order the controller already mutated rather than a fresh read.
     */
    private function forgetHydratedOrders(): void
    {
        OrderTableMap::clearInstancePool();
        OrderTableMap::clearRelatedInstancePool();
    }
}
