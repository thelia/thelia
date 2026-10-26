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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpFoundation\Session\SessionFactory;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;

/**
 * The status of an order written through `PATCH /api/admin/orders/{id}`, over real HTTP.
 *
 * The processor tells what the client asked for by comparing the status of the resource
 * with the one of the previous data API Platform keeps, a shallow clone of what the
 * provider read: were the two to share the status object, a deserialization that changes
 * it in place would make the change look like no change, and the status would be ignored
 * without an error.
 */
final class OrderStatusPatchApiTest extends ApiTestCase
{
    /** @var list<int> the status ids carried by the ORDER_UPDATE_STATUS events dispatched */
    private array $statusEvents = [];

    public function testAPatchThatChangesTheStatusMovesItThroughTheEvent(): void
    {
        $order = $this->orderWithItsLines();
        $this->listenToTheStatusEvents();
        $cancelled = $this->statusId(OrderStatus::CODE_CANCELED);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$cancelled,
            'orderProducts' => $this->lineIris($order),
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([$cancelled], $this->statusEvents, 'One status event, for the status asked.');
        self::assertSame($cancelled, $this->storedStatusId($order), 'The status is written.');
    }

    /**
     * The API is stateless: its admin lives in the security token and in no session. The
     * line the status change leaves on the history of the order is the administrator's.
     */
    public function testAPatchThatChangesTheStatusIsWrittenOnTheHistoryUnderTheAdministrator(): void
    {
        $order = $this->orderWithItsLines();
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);
        $cancelled = $this->statusId(OrderStatus::CODE_CANCELED);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$cancelled,
            'orderProducts' => $this->lineIris($order),
        ], $this->authenticateAsAdmin($admin), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        $lines = OrderHistoryQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->find($this->getPropelConnection())
            ->getData();

        self::assertCount(1, $lines);
        self::assertSame(OrderHistoryActorType::ADMIN->value, $lines[0]->getActorType());
        self::assertSame($admin->getLogin(), $lines[0]->getActorLabel());
        self::assertSame((int) $admin->getId(), $lines[0]->getAdminId());
    }

    /**
     * The session cookie of the browser reaches /api (its path is /), and the request is
     * the one of the JWT it carries, not of the administrator signed in to the back office
     * in that browser.
     */
    public function testTheAuthorOfAStatusChangeIsTheOneOfTheJwtAndNotOfTheSessionCookie(): void
    {
        $order = $this->orderWithItsLines();
        $factory = $this->createFixtureFactory();
        $sessionAdmin = $factory->admin(['password' => 'password']);
        $tokenAdmin = $factory->admin(['password' => 'password']);
        $token = $this->authenticateAsAdmin($tokenAdmin);

        $session = $this->getService(SessionFactory::class)->createSession();
        self::assertInstanceOf(Session::class, $session);
        $session->set('thelia.admin_user', $sessionAdmin);
        $session->save();

        // The session of the test environment is a mock that does not read the cookie of
        // the request: binding it to the one just written is what the cookie does in
        // production, right after KernelListener::initializeSession() gave the request a
        // session.
        $sessionId = $session->getId();
        $bindSession = static function (RequestEvent $event) use ($sessionId): void {
            $event->getRequest()->getSession()->setId($sessionId);
        };
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);

        // The witness: what the session of the request holds while it is handled. Were the
        // harness to make the session invisible, the test would pass whatever the resolver
        // does, and this is what says so.
        $seenInSession = [];
        $witness = static function () use (&$seenInSession): void {
            $seenInSession[] = static::getContainer()->get(SecurityContext::class)->getAdminUser()?->getId();
        };

        // The kernel of the test leaves the request it booted on ("/", without a session)
        // at the bottom of the stack, and SecurityContext reads the main request: without
        // emptying it the session of the request under test is never looked at.
        $requestStack = static::getContainer()->get(RequestStack::class);
        $bootRequests = [];
        while (null !== ($bootRequest = $requestStack->pop())) {
            array_unshift($bootRequests, $bootRequest);
        }

        $dispatcher->addListener(KernelEvents::REQUEST, $bindSession, \PHP_INT_MAX - 1);
        $dispatcher->addListener(KernelEvents::CONTROLLER, $witness);

        try {
            $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
                'orderStatus' => '/api/admin/order_statutes/'.$this->statusId(OrderStatus::CODE_CANCELED),
                'orderProducts' => $this->lineIris($order),
            ], $token, 'merge-patch+json');
        } finally {
            $dispatcher->removeListener(KernelEvents::REQUEST, $bindSession);
            $dispatcher->removeListener(KernelEvents::CONTROLLER, $witness);

            while (null !== $requestStack->pop()) {
            }

            foreach ($bootRequests as $bootRequest) {
                $requestStack->push($bootRequest);
            }
        }

        self::assertSame([(int) $sessionAdmin->getId()], array_map('intval', $seenInSession), 'The session admin was not visible to the request: the test proves nothing.');
        self::assertJsonResponseSuccessful($response);

        $lines = OrderHistoryQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->find($this->getPropelConnection())
            ->getData();

        self::assertCount(1, $lines);
        self::assertSame($tokenAdmin->getLogin(), $lines[0]->getActorLabel());
        self::assertSame((int) $tokenAdmin->getId(), $lines[0]->getAdminId());
    }

    public function testAPatchThatDoesNotSpeakOfTheStatusLeavesItAloneAndRaisesNoEvent(): void
    {
        $order = $this->orderWithItsLines();
        $before = $this->storedStatusId($order);
        $this->listenToTheStatusEvents();

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'deliveryRef' => 'TRACK-42',
            'orderProducts' => $this->lineIris($order),
        ], $this->authenticateAsAdmin(), 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);
        self::assertSame([], $this->statusEvents, 'No status event for a status nobody asked for.');
        self::assertSame($before, $this->storedStatusId($order), 'The status is left as it was.');
        self::assertSame('TRACK-42', OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())?->getDeliveryRef());
    }

    private function orderWithItsLines(): Order
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $line = $factory->orderProduct($order);
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2')->save($this->getPropelConnection());

        return $order;
    }

    /**
     * @return list<string>
     */
    private function lineIris(Order $order): array
    {
        $ids = [];

        foreach ($order->getOrderProducts() as $line) {
            $ids[] = '/api/admin/order_products/'.$line->getId();
        }

        self::assertNotSame([], $ids);

        return $ids;
    }

    private function listenToTheStatusEvents(): void
    {
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::ORDER_UPDATE_STATUS, function (OrderEvent $event): void {
            $this->statusEvents[] = (int) $event->getStatus();
        }, 200);
    }

    private function statusId(string $code): int
    {
        return (int) (OrderStatusQuery::create()->findOneByCode($code, $this->getPropelConnection())?->getId() ?? throw new \RuntimeException('No status '.$code));
    }

    private function storedStatusId(Order $order): int
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `status_id` FROM `order` WHERE `id` = ?');
        $statement->execute([(int) $order->getId()]);

        return (int) $statement->fetchColumn();
    }
}
