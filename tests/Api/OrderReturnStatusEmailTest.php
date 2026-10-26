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

namespace Thelia\Tests\Api;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\OrderReturnStatusEmailDispatcher;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Event\OrderHistoryEvent;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The customer is told of a return once per gesture, whichever way the gesture
 * came in: the two creation routes of the API, the transition route of the
 * administration, and the three events a back office or a module dispatches.
 * Never twice, never for a gesture that was refused or rolled back, and only
 * once the write is committed and the line that records it is on the order.
 *
 * The mails are counted where they leave the shop, in the mailer, and the
 * dispatch of ORDER_RETURN_SEND_STATUS_EMAIL is observed from in front of the
 * listener that sends it.
 */
final class OrderReturnStatusEmailTest extends ApiTestCase
{
    private const STORE_EMAIL = 'shop@test.com';

    private FixtureFactory $factory;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * What each ORDER_RETURN_SEND_STATUS_EMAIL dispatch found when it was made.
     *
     * @var list<array{nested: int, history: list<string>}>
     */
    private array $announced = [];

    /**
     * The nested transaction count each RETURN_OPENED line was inserted at.
     *
     * @var list<int>
     */
    private array $openingInsertions = [];

    private int $baselineNestedTransactions;

    /**
     * @var callable(OrderReturnEvent): void
     */
    private $observer;

    /**
     * @var callable(OrderHistoryEvent): void
     */
    private $insertionObserver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->eventDispatcher = $this->getService(EventDispatcherInterface::class);

        // A shop without a sender address sends nothing at all, and the test
        // database is seeded without one.
        ConfigQuery::write('store_email', self::STORE_EMAIL);
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        ConfigQuery::write('order_return_window_days', '14');

        $this->baselineNestedTransactions = $this->getPropelConnection()->getNestedTransactionCount();
        $this->observer = function (OrderReturnEvent $event): void {
            $orderId = (int) $event->getOrderReturn()->getOrderId();

            $this->announced[] = [
                'nested' => $this->getPropelConnection()->getNestedTransactionCount(),
                'history' => array_map(
                    'strval',
                    OrderHistoryQuery::create()
                        ->filterByOrderId($orderId)
                        ->orderById()
                        ->select(['EventType'])
                        ->find($this->getPropelConnection())
                        ->getData(),
                ),
            ];
        };
        $this->eventDispatcher->addListener(TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL, $this->observer, 256);

        $this->insertionObserver = function (OrderHistoryEvent $event): void {
            if (OrderHistoryEventType::RETURN_OPENED->value === $event->getModel()->getEventType()) {
                $this->openingInsertions[] = $this->getPropelConnection()->getNestedTransactionCount();
            }
        };
        $this->eventDispatcher->addListener(OrderHistoryEvent::POST_INSERT, $this->insertionObserver);
    }

    protected function tearDown(): void
    {
        $this->eventDispatcher->removeListener(TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL, $this->observer);
        $this->eventDispatcher->removeListener(OrderHistoryEvent::POST_INSERT, $this->insertionObserver);
        OrderReturnStatusQuery::resetCache();

        try {
            parent::tearDown();
        } finally {
            ConfigTableMap::clearInstancePool();
            ConfigQuery::resetCache();
        }
    }

    public function testAFrontCreationMailsTheCustomerOnce(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(1, $this->mailsTo($customer), 'One mail is owed for the opening of the return.');
        self::assertCount(1, $this->announced);
    }

    public function testAnAdminCreationMailsTheCustomerOnce(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(1, $this->mailsTo($customer));
        self::assertCount(1, $this->announced);
    }

    public function testAnAdminTransitionMailsTheCustomerOnce(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_REQUESTED);

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_ACCEPTED],
            token: $this->authenticateAsAdmin(),
        );

        self::assertJsonResponseSuccessful($response);
        self::assertCount(1, $this->mailsTo($customer));
        self::assertCount(1, $this->announced);
    }

    /**
     * The back office and a module go through the events, not the API: each of
     * the three gestures is one mail, announced after the line that records it.
     */
    public function testEachEventGestureMailsTheCustomerOnceAfterItsHistoryLine(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer, quantity: 3.0);

        $return = (new OrderReturn())->setOrder($order)->setCustomer($customer)->setCreatedByAdmin(true);
        $return->addOrderReturnLine((new OrderReturnLine())->setOrderProduct($orderProduct)->setQuantity(2.0));
        $this->eventDispatcher->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);

        self::assertCount(1, $this->mailsTo($customer), 'ORDER_RETURN_CREATE');
        self::assertSame(OrderHistoryEventType::RETURN_OPENED->value, $this->lastHistoryLine(0));

        $accepted = (int) OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_ACCEPTED);
        $this->eventDispatcher->dispatch(
            (new OrderReturnEvent($return))->setTargetStatusId($accepted),
            TheliaEvents::ORDER_RETURN_UPDATE_STATUS,
        );

        self::assertCount(2, $this->mailsTo($customer), 'ORDER_RETURN_UPDATE_STATUS');
        self::assertSame(OrderHistoryEventType::RETURN_STATUS_CHANGED->value, $this->lastHistoryLine(1));

        $line = $return->getOrderReturnLines()->getFirst();
        $this->eventDispatcher->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([(int) $line->getId() => ['quantity' => 2.0, 'resellable' => true]]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );

        self::assertCount(3, $this->mailsTo($customer), 'ORDER_RETURN_RECEIVE');
        self::assertSame(OrderHistoryEventType::RETURN_RECEIVED->value, $this->lastHistoryLine(2));
        self::assertCount(3, $this->announced);
    }

    /**
     * The API processors do not dispatch ORDER_RETURN_CREATE, so the line that
     * opens a return on the order is theirs to write: once, after the commit,
     * before the mail that announces it, under the name of whoever opened it.
     */
    public function testAFrontCreationWritesTheOpeningLineBeforeTheMailUnderTheCustomer(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()->filterByOrderId((int) $order->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($created);

        $lines = $this->openingLinesOf($order);
        self::assertCount(1, $lines, 'One line is owed for the opening of the return.');
        self::assertSame(OrderHistoryActorType::CUSTOMER->value, $lines[0]->getActorType());
        self::assertSame($customer->getRef(), $lines[0]->getActorLabel());
        self::assertSame(['return_ref' => $created->getRef()], json_decode((string) $lines[0]->getPayload(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(OrderHistoryEventType::RETURN_OPENED->value, $this->lastHistoryLine(0), 'The mail went out before the line that records the opening.');
        self::assertSame([$this->baselineNestedTransactions], $this->openingInsertions, 'The line was written while the transaction of the write was still open.');
        self::assertCount(1, $this->announced);
    }

    public function testAnAdminCreationWritesTheOpeningLineBeforeTheMailUnderTheAdministrator(): void
    {
        $customer = $this->customer();
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsAdmin($admin),
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()->filterByOrderId((int) $order->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($created);

        $lines = $this->openingLinesOf($order);
        self::assertCount(1, $lines, 'One line is owed for the opening of the return.');
        self::assertSame(OrderHistoryActorType::ADMIN->value, $lines[0]->getActorType());
        self::assertSame($admin->getLogin(), $lines[0]->getActorLabel());
        self::assertSame((int) $admin->getId(), $lines[0]->getAdminId());
        self::assertSame(['return_ref' => $created->getRef()], json_decode((string) $lines[0]->getPayload(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(OrderHistoryEventType::RETURN_OPENED->value, $this->lastHistoryLine(0), 'The mail went out before the line that records the opening.');
        self::assertSame([$this->baselineNestedTransactions], $this->openingInsertions, 'The line was written while the transaction of the write was still open.');
        self::assertCount(1, $this->announced);
    }

    public function testARefusedCreationWritesNoOpeningLine(): void
    {
        $customer = $this->customer();
        $stranger = $this->customer();
        [$strangerOrder, $strangerProduct] = $this->paidOrderWithProduct($stranger);
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $front = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$strangerOrder->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$strangerProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsCustomer($customer),
        );
        $admin = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 99.0],
                ],
            ],
            token: $this->authenticateAsAdmin(),
        );

        self::assertGreaterThanOrEqual(400, $front->getStatusCode());
        self::assertSame(422, $admin->getStatusCode());
        self::assertSame([], $this->openingLinesOf($strangerOrder));
        self::assertSame([], $this->openingLinesOf($order));
    }

    public function testAMailIsOnlySentOnceTheWriteIsCommitted(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        $created = OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->findOne($this->getPropelConnection());
        self::assertNotNull($created);

        $this->eventDispatcher->dispatch(
            (new OrderReturnEvent($created))->setTargetStatusId((int) OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_ACCEPTED)),
            TheliaEvents::ORDER_RETURN_UPDATE_STATUS,
        );

        self::assertCount(2, $this->announced);

        foreach ($this->announced as $announcement) {
            self::assertSame(
                $this->baselineNestedTransactions,
                $announcement['nested'],
                'The mail went out while the transaction of the write was still open.',
            );
        }
    }

    public function testARefusedFrontCreationSendsNothing(): void
    {
        $customer = $this->customer();
        $stranger = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($stranger);

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $this->authenticateAsCustomer($customer),
        );

        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
        self::assertSame([], $this->allMails());
        self::assertSame([], $this->announced);
    }

    public function testARefusedAdminCreationSendsNothing(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 99.0],
                ],
            ],
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $this->allMails());
        self::assertSame([], $this->announced);
    }

    public function testARefusedTransitionSendsNothing(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_REQUESTED);

        // requested -> received is not an authorized edge.
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_RECEIVED],
            token: $this->authenticateAsAdmin(),
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $this->allMails());
        self::assertSame([], $this->announced);
    }

    public function testAnEventGestureThatThrowsSendsNothingAndLeavesNoReturn(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer, quantity: 1.0);

        // Two units asked of a line that holds one: refused under the lock, the
        // transaction rolled back.
        $return = (new OrderReturn())->setOrder($order)->setCustomer($customer)->setCreatedByAdmin(true);
        $return->addOrderReturnLine((new OrderReturnLine())->setOrderProduct($orderProduct)->setQuantity(2.0));

        try {
            $this->eventDispatcher->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
            self::fail('The creation was not refused.');
        } catch (ReturnNotAllowedException) {
        }

        self::assertSame([], $this->allMails());
        self::assertSame([], $this->announced);
        self::assertSame(0, OrderReturnQuery::create()->filterByOrderId((int) $order->getId())->count($this->getPropelConnection()));

        $illegal = $this->returnWithLine($customer, OrderReturnStatus::CODE_REQUESTED);

        try {
            $this->eventDispatcher->dispatch(
                (new OrderReturnEvent($illegal))->setTargetStatusId((int) OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_SETTLED)),
                TheliaEvents::ORDER_RETURN_UPDATE_STATUS,
            );
            self::fail('The transition was not refused.');
        } catch (ReturnNotAllowedException) {
        }

        self::assertSame([], $this->allMails());
        self::assertSame([], $this->announced);
    }

    public function testTheServiceDispatchesTheMailEventOnceWithWhatItWasGiven(): void
    {
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(
            TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL,
            static function (OrderReturnEvent $event) use (&$received): void {
                $received[] = $event;
            },
        );
        $service = new OrderReturnStatusEmailDispatcher($dispatcher);
        $return = new OrderReturn();

        $service->dispatchFor($return);

        self::assertCount(1, $received);
        self::assertSame($return, $received[0]->getOrderReturn());

        $event = (new OrderReturnEvent($return))->setRefusalReason('Used.');
        $service->dispatchEvent($event);

        self::assertCount(2, $received);
        self::assertSame($event, $received[1], 'A mail listener must read the event the gesture carried.');
    }

    /**
     * The newest line of the order when the mail of the nth gesture was
     * announced: the mails the earlier gestures sent leave their own lines, so
     * only the last one says what the mail follows.
     */
    private function lastHistoryLine(int $announcement): ?string
    {
        $history = $this->announced[$announcement]['history'] ?? [];

        return [] === $history ? null : $history[array_key_last($history)];
    }

    /**
     * @return list<OrderHistory>
     */
    private function openingLinesOf(Order $order): array
    {
        return array_values(OrderHistoryQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByEventType(OrderHistoryEventType::RETURN_OPENED->value)
            ->orderById()
            ->find($this->getPropelConnection())
            ->getData());
    }

    /**
     * @return list<Email>
     */
    private function allMails(): array
    {
        return array_values(array_filter(
            self::getMailerMessages(),
            static fn (RawMessage $message): bool => $message instanceof Email,
        ));
    }

    /**
     * @return list<Email>
     */
    private function mailsTo(Customer $customer): array
    {
        return array_values(array_filter(
            $this->allMails(),
            static fn (Email $email): bool => \in_array(
                $customer->getEmail(),
                array_map(static fn (Address $to): string => $to->getAddress(), $email->getTo()),
                true,
            ),
        ));
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * @return array{0: Order, 1: OrderProductModel}
     */
    private function paidOrderWithProduct(Customer $customer, float $quantity = 1.0): array
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        return [$order, $this->orderProductFor($order, $quantity)];
    }

    private function returnWithLine(Customer $customer, string $statusCode): OrderReturn
    {
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode($statusCode));
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId($orderProduct->getProductSaleElementsId())
            ->setQuantity(1.0)
            ->setQuantityReceived(0.0)
            ->save($this->getPropelConnection());

        $return->clearOrderReturnLines();

        return $return;
    }

    private function orderProductFor(Order $order, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}
