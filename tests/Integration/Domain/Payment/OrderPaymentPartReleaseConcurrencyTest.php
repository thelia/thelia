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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderStatus as OrderStatusResource;
use Thelia\Api\State\Processor\OrderProcessor;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\TestToken;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;

/**
 * The parts of an order settled and released by two requests at once, and a refused
 * settlement rolled back for real.
 *
 * Every other test of the parts runs inside the transaction IntegrationTestCase wraps
 * around it, where a rollback of the code under test only takes a nesting level off and
 * nothing another session commits is at stake. Here the fixtures are committed, and
 * cleaned up by hand; the competing request is a second PHP process, with its own kernel
 * and connection, started while this one holds the locks.
 */
#[Group('concurrency')]
final class OrderPaymentPartReleaseConcurrencyTest extends IntegrationTestCase
{
    use ForgetsPooledModels;

    protected bool $useTransaction = false;

    private const PROCESS_TIMEOUT = 60;

    private ?ConnectionInterface $otherSession = null;

    /** @var list<int> */
    private array $moduleIds = [];

    /** @var list<int> */
    private array $orderIds = [];

    /** @var list<int> */
    private array $orderAddressIds = [];

    /** @var list<int> */
    private array $cartIds = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var array<string, Module> */
    private array $modules = [];

    /** @var list<string> */
    private array $debitNames = [];

    protected function setUp(): void
    {
        parent::setUp();

        InstantTestPaymentModule::reset();
        SecondInstantTestPaymentModule::reset();
        BalanceChargingTestPaymentModule::reset();
        $this->otherSession = $this->openOtherSession();

        foreach ([InstantTestPaymentModule::class, SecondInstantTestPaymentModule::class, BalanceChargingTestPaymentModule::class] as $moduleClass) {
            $this->modules[$moduleClass] = $this->committedModule($moduleClass);
        }
    }

    protected function tearDown(): void
    {
        try {
            $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->deleteFixtures();
        } finally {
            foreach (array_keys($this->modules) as $moduleClass) {
                static::getContainer()->set('module.'.$moduleClass::getModuleCode(), null);
            }

            InstantTestPaymentModule::reset();
            SecondInstantTestPaymentModule::reset();
            BalanceChargingTestPaymentModule::reset();
            self::forgetPooledModels();
            Propel::disableInstancePooling();

            parent::tearDown();
        }
    }

    public function testTheOtherProcessWorksOnTheDatabaseOfThisProcess(): void
    {
        if ('' !== TestToken::suffix()) {
            self::assertStringEndsWith(TestToken::suffix(), (string) $this->otherSession?->query('SELECT DATABASE()')->fetchColumn());
        }

        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);

        $outcome = $this->waitFor($this->cancelInAnotherProcess($order));

        self::assertSame([], $outcome['calls']);
        self::assertSame([OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value], $this->storedStatesOf($order));
    }

    /**
     * A refused settlement of the second instant part: the first was settled in the same
     * transaction, is given back to its module, and the rollback leaves it pending in the
     * database, as another session reads it.
     */
    public function testARefusedSettlementLeavesTheFirstPartPendingOnceRolledBack(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [SecondInstantTestPaymentModule::class, '20', OrderPaymentPartState::PENDING, false, 1],
            [BalanceChargingTestPaymentModule::class, '50', OrderPaymentPartState::PENDING, true, 2],
        ]);
        SecondInstantTestPaymentModule::$secondRefusesEveryPart = true;

        try {
            $this->getService(OrderPaymentPartSettlement::class)->settleInstantPartsOf($this->reread($order));
            self::fail('The settlement must be refused.');
        } catch (InstantPaymentRefusedException) {
        }

        self::assertFalse(Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->inTransaction(), 'The transaction is over.');
        self::assertSame(['settle', 'settle', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            $this->storedStatesOf($order),
            'Read by another session: nothing is settled in the database.',
        );
    }

    /**
     * Two cancellations of the same order at once: the second waits on the lock the first
     * holds while it asks the module to release the card, then reads the part released.
     */
    public function testTwoCancellationsAtOnceReleaseThePartOnce(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::SETTLED, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $instantPartId = $this->partIdsOf($order)[0];
        $process = null;
        InstantTestPaymentModule::$whenAskedToRelease = function () use ($order, &$process): void {
            $process = $this->cancelInAnotherProcess($order);
            $this->waitUntilTheOtherProcessWaitsOnTheOrder($process);
        };

        $this->reread($order)->setCancelled($this->dispatcher());
        self::assertNotNull($process, 'The module was asked to release the part.');
        $outcome = $this->waitFor($process);

        self::assertSame([$instantPartId], InstantTestPaymentModule::$releasedPartIds, 'Released by this process.');
        self::assertSame([], $outcome['calls'], 'The other cancellation asked the module nothing.');
        self::assertSame([OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value], $this->storedStatesOf($order));
    }

    /**
     * A cancellation that comes while the instant parts are being settled waits for the
     * settlement, then gives back what it settled: a part is never left both given back
     * and settled.
     */
    public function testACancellationDuringTheSettlementReleasesWhatWasSettled(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $instantPartId = $this->partIdsOf($order)[0];
        $process = null;
        InstantTestPaymentModule::$whenAskedToSettle = function () use ($order, &$process): void {
            $process = $this->cancelInAnotherProcess($order);
            $this->waitUntilTheOtherProcessWaitsOnTheOrder($process);
        };

        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
        self::assertNotNull($process, 'The module was asked to settle the part.');
        $outcome = $this->waitFor($process);

        self::assertSame([$instantPartId], InstantTestPaymentModule::$settledPartIds);
        self::assertSame([$instantPartId], $outcome['releasedPartIds'], 'What this process settled, the other gave back, once.');
        self::assertSame(['release'], $outcome['calls']);
        self::assertTrue($this->reread($order)->isCancelled());
        self::assertSame([OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value], $this->storedStatesOf($order));
    }

    /**
     * The reverse order: a cancellation holds the lock, giving back a card, when the
     * payment is attempted. The attempt waits until the cancellation is over, then reads
     * the order cancelled and is refused: a part released while pending is never spent
     * afterwards, and the provider is not asked to pay.
     */
    public function testAPaymentDuringACancellationSettlesNothing(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::SETTLED, false, 0],
            [SecondInstantTestPaymentModule::class, '20', OrderPaymentPartState::PENDING, false, 1],
            [BalanceChargingTestPaymentModule::class, '50', OrderPaymentPartState::PENDING, true, 2],
        ]);
        $firstPartId = $this->partIdsOf($order)[0];
        $marker = \sprintf('%s/var/release-concurrency-%s.marker', \dirname(__DIR__, 4), bin2hex(random_bytes(6)));
        $this->markerFiles[] = $marker;
        $process = $this->cancelInAnotherProcess($order, $marker);
        $this->waitForTheMarker($process, $marker);

        $startedAt = microtime(true);

        try {
            $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);
            self::fail('The payment of the order cancelled meanwhile must be refused.');
        } catch (CancelledOrderPaymentRefusedException) {
        }

        $endedAt = microtime(true);
        $outcome = $this->waitFor($process);

        // The other process holds the lock for 1.5 s once the marker is written: an attempt
        // that did not wait on it would be over in a few milliseconds.
        self::assertGreaterThan(1.0, $endedAt - $startedAt, 'The payment waited on the lock of the cancellation.');
        self::assertGreaterThanOrEqual($outcome['releasedAt'], $endedAt, 'And was over only once the card was given back.');
        self::assertSame(['release'], $outcome['calls']);
        self::assertSame([$firstPartId], $outcome['releasedPartIds']);
        self::assertSame([], BalanceChargingTestPaymentModule::$paidOrderIds, 'The provider is not asked to pay.');
        self::assertSame([], InstantTestPaymentModule::$settledPartIds, 'The pending card part, released meanwhile, is not spent.');
        self::assertSame(
            [OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value],
            $this->storedStatesOf($order),
        );
        self::assertTrue($this->reread($order)->isCancelled());
    }

    /**
     * The admin API cancels an order while another request settles one of its instant parts.
     * The other request commits its debit after the first read of the API request: were the
     * order not locked before that read, the release would run in a view frozen before the
     * debit, and the module asked to give the part back would not find what it was asked to
     * give back (a credit lost). With the lock taken first, the settlement waits for the API
     * request, then finds the order cancelled and refuses: no debit is ever committed.
     *
     * Everything but the persistence is the real code: the processor, the event, the
     * listener, the settlement, the transaction and the second process.
     */
    public function testACancellationThroughTheAdminApiLocksTheOrderBeforeItReadsAnything(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $this->addTheLinesAdminWritesRequire($order);
        $debitName = 'release-concurrency-debit-'.bin2hex(random_bytes(6));
        $this->debitNames[] = $debitName;
        $process = null;

        // The persistence of the API request reads the order, then writes it: it holds no hook
        // between the two, so this one stands in the gap. The read is the plain one the
        // persistence starts with, which freezes the view of the transaction unless the order
        // was locked before; the other request settles the part then, and commits unless it
        // has to wait on the lock of the order.
        $startsTheOtherRequest = function () use ($order, $debitName, &$process): void {
            Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->query('SELECT `status_id` FROM `order` WHERE `id` = '.(int) $order->getId())->fetchColumn();
            $process = $this->settleInAnotherProcess($order, $debitName);
            $this->waitUntilTheOtherProcessWaitsOnTheOrder($process, true);
        };

        // What a module that keeps its own debits finds when asked to give the part back:
        // a plain read, in the view of the transaction of the API request.
        $debitSeenWhenReleased = [];
        InstantTestPaymentModule::$whenAskedToRelease = static function () use ($debitName, &$debitSeenWhenReleased): void {
            $statement = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->prepare('SELECT COUNT(*) FROM `config` WHERE `name` = ?');
            $statement->execute([$debitName]);
            $debitSeenWhenReleased[] = 1 === (int) $statement->fetchColumn();
        };

        $request = Request::create('http://localhost/api/admin/orders/'.$order->getId(), 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $request->attributes->set('id', $order->getId());

        $persistProcessor = $this->getService(PropelPersistProcessor::class);
        $processor = new OrderProcessor(
            new readonly class($this->getService(ApiResourcePropelTransformerService::class), $this->getService(RequestStack::class), $this->dispatcher(), $startsTheOtherRequest, $persistProcessor) extends PropelPersistProcessor {
                public function __construct(
                    ApiResourcePropelTransformerService $transformer,
                    RequestStack $requestStack,
                    EventDispatcherInterface $dispatcher,
                    private \Closure $inTheGap,
                    private PropelPersistProcessor $persistProcessor,
                ) {
                    parent::__construct($transformer, $requestStack, $dispatcher);
                }

                public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
                {
                    ($this->inTheGap)();

                    return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
                }
            },
            $this->dispatcher(),
            $this->getService(ApiResourcePropelTransformerService::class),
        );

        $processor->process(
            $this->orderResourceSetTo($order, OrderStatus::CODE_CANCELED),
            new Patch(uriTemplate: '/admin/orders/{id}', denormalizationContext: ['groups' => [OrderResource::GROUP_ADMIN_WRITE]]),
            ['id' => $order->getId()],
            ['request' => $request],
        );

        self::assertNotNull($process, 'The other request was started.');
        $other = $this->waitFor($process);
        $statement = $this->otherSession?->prepare('SELECT COUNT(*) FROM `config` WHERE `name` = ?');
        self::assertNotNull($statement);
        $statement->execute([$debitName]);
        $debitIsCommitted = 1 === (int) $statement->fetchColumn();

        // A debit committed before the release must be found by the module asked to give the
        // part back; a debit never committed leaves nothing to give back.
        self::assertSame(
            $debitIsCommitted ? [true] : [],
            $debitSeenWhenReleased,
            'The module found, when the part was released, the debit the other request had committed.',
        );
        self::assertSame('refused', $other['outcome'], 'The settlement waited for the cancellation, then found the order cancelled.');
        self::assertFalse($debitIsCommitted);
        self::assertTrue($this->reread($order)->isCancelled());
        self::assertSame([OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value], $this->storedStatesOf($order));
    }

    /**
     * A request that never speaks of the status writes a delivery reference while another
     * request has moved the order on since the provider read it: the status stays what the
     * other request committed, and no status event is raised for a change nobody asked for.
     *
     * @return iterable<string, array{string}>
     */
    public static function statusesAnotherRequestCommits(): iterable
    {
        yield 'a cancellation, whose parts the other request released' => [OrderStatus::CODE_CANCELED];
        yield 'a status the order may leave' => [OrderStatus::CODE_PROCESSING];
    }

    #[DataProvider('statusesAnotherRequestCommits')]
    public function testAWriteThatDoesNotSpeakOfTheStatusKeepsTheOneAnotherRequestCommitted(string $committedStatusCode): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $this->addTheLinesAdminWritesRequire($order);
        [$resource, $previous] = $this->orderAsTheProviderReadsIt($order);
        $this->anotherRequestMovesTheOrderTo($order, $committedStatusCode);
        $events = 0;
        $counts = static function () use (&$events): void {
            ++$events;
        };
        $this->dispatcher()->addListener(TheliaEvents::ORDER_UPDATE_STATUS, $counts, 200);

        try {
            $resource->deliveryRef = 'TRACK-1';
            $this->patchThroughTheProcessor($order, $resource, $previous);
        } finally {
            $this->dispatcher()->removeListener(TheliaEvents::ORDER_UPDATE_STATUS, $counts);
        }

        self::assertSame(0, $events, 'No status event for a status nobody asked for.');
        self::assertSame($committedStatusCode, $this->storedStatusCodeOf($order), 'The status the other request committed is kept.');
        self::assertSame('TRACK-1', $this->storedDeliveryRefOf($order), 'The field of the request is written.');
    }

    /**
     * The ordinary change of a status through the admin API still goes through the event,
     * with the release of the payment parts a cancellation brings.
     */
    public function testAnOrdinaryStatusChangeStillGoesThroughTheEvent(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::SETTLED, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $this->addTheLinesAdminWritesRequire($order);
        [, $previous] = $this->orderAsTheProviderReadsIt($order);
        $resource = $this->orderResourceSetTo($order, OrderStatus::CODE_CANCELED);
        $events = [];
        $counts = static function (OrderEvent $event) use (&$events): void {
            $events[] = $event->getStatus();
        };
        $this->dispatcher()->addListener(TheliaEvents::ORDER_UPDATE_STATUS, $counts, 200);

        try {
            $this->patchThroughTheProcessor($order, $resource, $previous);
        } finally {
            $this->dispatcher()->removeListener(TheliaEvents::ORDER_UPDATE_STATUS, $counts);
        }

        self::assertSame([OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED)?->getId()], $events, 'One status event, for the status asked.');
        self::assertSame(OrderStatus::CODE_CANCELED, $this->storedStatusCodeOf($order));
        self::assertSame([OrderPaymentPartState::RELEASED->value, OrderPaymentPartState::RELEASED->value], $this->storedStatesOf($order));
        self::assertSame([$this->partIdsOf($order)[0]], InstantTestPaymentModule::$releasedPartIds);
    }

    /**
     * The order is deleted by another request after the processor read it (the instance pool,
     * on in production, still hands the deleted order back) and before it took the lock: the
     * row under the lock is gone, which is a 404, not a write on a deleted order.
     */
    public function testAnOrderDeletedBeforeTheLockIsNotFound(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $this->addTheLinesAdminWritesRequire($order);
        [$resource, $previous] = $this->orderAsTheProviderReadsIt($order);
        Propel::enableInstancePooling();
        self::assertNotNull(OrderQuery::create()->findPk($order->getId()), 'The order is in the pool.');
        $this->otherSession?->exec('DELETE FROM `order_payment_part` WHERE `order_id` = '.(int) $order->getId());
        $this->otherSession?->exec('DELETE FROM `order_version` WHERE `id` = '.(int) $order->getId());
        self::assertSame(1, $this->otherSession?->exec('DELETE FROM `order` WHERE `id` = '.(int) $order->getId()), 'The other request deleted the order.');

        try {
            $this->patchThroughTheProcessor($order, $resource, $previous);
            self::fail('The order is gone.');
        } catch (NotFoundHttpException) {
        }

        self::assertFalse(Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->inTransaction(), 'The transaction is over.');
    }

    /**
     * A module that rolls back, on the connection of the core, a transaction it did not
     * open, leaves the release unable to commit: the other modules are not asked to give
     * back a part whose release would be rolled back, and the failure is not swallowed.
     */
    public function testAModuleThatRollsBackTheTransactionOfTheReleaseStopsIt(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::SETTLED, false, 0],
            [SecondInstantTestPaymentModule::class, '20', OrderPaymentPartState::SETTLED, false, 1],
            [BalanceChargingTestPaymentModule::class, '50', OrderPaymentPartState::PENDING, true, 2],
        ]);
        $poisoned = false;
        InstantTestPaymentModule::$whenAskedToRelease = static function () use (&$poisoned): void {
            if ($poisoned) {
                return;
            }

            $poisoned = true;
            $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
            $connection->beginTransaction();
            $connection->rollBack();

            throw new \RuntimeException('The module gave up and rolled back.');
        };

        try {
            $this->reread($order)->setCancelled($this->dispatcher());
            self::fail('A release that cannot be committed must not pass for done.');
        } catch (\LogicException $failure) {
            self::assertStringContainsString('rolled back a transaction it did not open', $failure->getMessage());
        }

        self::assertSame(['release'], InstantTestPaymentModule::$calls, 'The first part is not asked once the release cannot be committed.');
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->storedStatesOf($order),
            'Nothing of the release is committed.',
        );
        self::assertTrue($this->reread($order)->isCancelled(), 'The cancellation, committed before, stands.');
    }

    /**
     * A module that rolls back the only level of the transaction, with no transaction of its
     * own opened: the rollback really happens, undoing what the release had written for the
     * parts given back before it, and the commit that follows has nothing to commit and
     * raises nothing. The release must still fail, and announce nothing: a part announced
     * released would be back to settled in the database.
     */
    public function testAModuleThatRollsBackTheOnlyLevelOfTheReleaseAnnouncesNothing(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::SETTLED, false, 0],
            [SecondInstantTestPaymentModule::class, '20', OrderPaymentPartState::SETTLED, false, 1],
            [BalanceChargingTestPaymentModule::class, '50', OrderPaymentPartState::PENDING, true, 2],
        ]);
        $asked = 0;
        // The parts are walked backwards: the second module gives its part back, then the
        // first one rolls back, alone.
        InstantTestPaymentModule::$whenAskedToRelease = static function () use (&$asked): void {
            if (2 !== ++$asked) {
                return;
            }

            Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->rollBack();
        };
        $announced = [];
        $listener = static function () use (&$announced): void {
            $announced[] = true;
        };
        $this->dispatcher()->addListener(TheliaEvents::ORDER_PAYMENT_PART_RELEASE, $listener);

        try {
            try {
                $this->reread($order)->setCancelled($this->dispatcher());
                self::fail('A release that cannot be committed must not pass for done.');
            } catch (\LogicException $failure) {
                self::assertStringContainsString('rolled back a transaction it did not open', $failure->getMessage());
            }
        } finally {
            $this->dispatcher()->removeListener(TheliaEvents::ORDER_PAYMENT_PART_RELEASE, $listener);
        }

        self::assertSame([], $announced, 'No part is announced released.');
        self::assertSame(['release', 'release'], InstantTestPaymentModule::$calls);
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->storedStatesOf($order),
            'What the rollback undid is read back as it was by another session, and nothing is written for the part whose module rolled back, which no longer holds a lock.',
        );
    }

    /**
     * A module that rolls back the only level while it settles a part: the settlement is
     * undone, and what it settled is given back to the module. The locks are gone, though:
     * another request may settle the part meanwhile, and the state of the part is not
     * written back to pending on a connection that has no transaction left.
     */
    public function testAModuleThatRollsBackTheOnlyLevelOfTheSettlementDoesNotOverwriteWhatAnotherRequestWrote(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $partId = $this->partIdsOf($order)[0];
        InstantTestPaymentModule::$whenAskedToSettle = static function (): void {
            Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->rollBack();
        };
        // The part is given back to its module, and in that very moment another request,
        // which the lost lock no longer holds back, settles it.
        InstantTestPaymentModule::$whenAskedToRelease = function () use ($partId): void {
            $this->otherSession?->exec(\sprintf("UPDATE `order_payment_part` SET `state` = 'settled' WHERE `id` = %d", $partId));
        };

        try {
            $this->getService(OrderPaymentPartSettlement::class)->settleInstantPartsOf($this->reread($order));
            self::fail('A settlement that cannot be committed must not pass for done.');
        } catch (\LogicException $failure) {
            self::assertStringContainsString('rolled back a transaction it did not open', $failure->getMessage());
        }

        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'What the module settled is given back.');
        self::assertSame(
            [OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value],
            $this->storedStatesOf($order),
            'The write of the other request is not overwritten.',
        );
    }

    /**
     * The same, at the settlement: the module that rolled back is caught right after it
     * answered, what it settled is given back, and nothing is committed.
     */
    public function testAModuleThatRollsBackTheTransactionOfTheSettlementStopsIt(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [SecondInstantTestPaymentModule::class, '20', OrderPaymentPartState::PENDING, false, 1],
            [BalanceChargingTestPaymentModule::class, '50', OrderPaymentPartState::PENDING, true, 2],
        ]);
        InstantTestPaymentModule::$whenAskedToSettle = static function (): void {
            $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
            $connection->beginTransaction();
            $connection->rollBack();
        };

        try {
            $this->getService(OrderPaymentPartSettlement::class)->settleInstantPartsOf($this->reread($order));
            self::fail('A settlement that cannot be committed must not pass for done.');
        } catch (\LogicException $failure) {
            self::assertStringContainsString('rolled back a transaction it did not open', $failure->getMessage());
        }

        self::assertSame(['settle', 'release'], InstantTestPaymentModule::$calls, 'The second part is not asked; the first is given back.');
        self::assertSame(
            [OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value, OrderPaymentPartState::PENDING->value],
            $this->storedStatesOf($order),
        );
    }

    /**
     * The parts of the order being settled are locked by their primary key, not by the
     * index on the order: a lock on that index also holds the gap after its last order, and
     * the parts of every order placed meanwhile would wait for the settlement, measured on
     * MariaDB 10.11 until the lock was released.
     */
    public function testASettlementDoesNotHoldBackThePartsOfANewerOrder(): void
    {
        $order = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, [
            [InstantTestPaymentModule::class, '30', OrderPaymentPartState::PENDING, false, 0],
            [BalanceChargingTestPaymentModule::class, '70', OrderPaymentPartState::PENDING, true, 1],
        ]);
        $newer = $this->committedOrderWithParts(OrderStatus::CODE_NOT_PAID, []);
        $moduleId = (int) $this->modules[BalanceChargingTestPaymentModule::class]->getId();
        $insert = null;
        InstantTestPaymentModule::$whenAskedToSettle = function () use ($newer, $moduleId, &$insert): void {
            $this->otherSession?->exec('SET SESSION innodb_lock_wait_timeout = 1');
            $startedAt = microtime(true);

            try {
                $this->otherSession?->exec(\sprintf(
                    'INSERT INTO `order_payment_part` (`order_id`, `payment_module_id`, `amount`, `state`, `is_primary`, `position`) VALUES (%d, %d, 100, \'pending\', 1, 0)',
                    (int) $newer->getId(),
                    $moduleId,
                ));
                $insert = microtime(true) - $startedAt;
            } catch (\PDOException $exception) {
                $insert = $exception->getMessage();
            }
        };

        $this->dispatcher()->dispatch(new OrderPaymentEvent($this->reread($order)), TheliaEvents::MODULE_PAY);

        self::assertIsFloat($insert, 'The part of the newer order was written while the settlement held its locks: '.var_export($insert, true));
        self::assertLessThan(0.5, $insert);
        self::assertSame([OrderPaymentPartState::SETTLED->value, OrderPaymentPartState::PENDING->value], $this->storedStatesOf($order));
        self::assertSame([OrderPaymentPartState::PENDING->value], $this->storedStatesOf($newer));
    }

    /** @var list<string> */
    private array $markerFiles = [];

    /**
     * @param resource $process
     */
    private function waitForTheMarker($process, string $marker): void
    {
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;

        while (!is_file($marker)) {
            if (!proc_get_status($process)['running']) {
                self::fail('The other process ended before releasing: '.stream_get_contents($this->pipes[(int) $process][2]));
            }

            if (microtime(true) > $deadline) {
                self::fail('The other process never released the part.');
            }

            usleep(50000);
        }
    }

    /**
     * @return resource
     */
    private function cancelInAnotherProcess(Order $order, ?string $marker = null)
    {
        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 3).'/Support/Payment/cancel-order-in-another-process.php', (string) $order->getId(), ...(null === $marker ? [] : [$marker])],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * @return resource
     */
    private function settleInAnotherProcess(Order $order, string $debitName)
    {
        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 3).'/Support/Payment/settle-instant-parts-in-another-process.php', (string) $order->getId(), $debitName],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * The order as the admin API hands it to the processor, with the status asked for.
     */
    private function orderResourceSetTo(Order $order, string $statusCode): OrderResource
    {
        $resource = $this->getService(ApiResourcePropelTransformerService::class)->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $this->reread($order),
            context: ['groups' => [OrderResource::GROUP_ADMIN_READ, OrderResource::GROUP_ADMIN_READ_SINGLE, OrderResource::GROUP_ADMIN_WRITE]],
        );
        self::assertInstanceOf(OrderResource::class, $resource);
        $statusId = OrderStatusQuery::create()->findOneByCode($statusCode)?->getId() ?? throw new \RuntimeException('No status '.$statusCode);

        return $resource->setOrderStatus((new OrderStatusResource())->setId($statusId));
    }

    /**
     * The admin write of an order requires its lines, which go with the order when it is deleted.
     */
    private function addTheLinesAdminWritesRequire(Order $order): void
    {
        $line = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('release-concurrency')
            ->setProductSaleElementsRef('release-concurrency')
            ->setTitle('Release concurrency')
            ->setQuantity(1)
            ->setPrice('100')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save();
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('20')->save();
    }

    /**
     * The order as the provider of the admin API reads it, and a second copy of it for the
     * processor to compare with (API Platform's previous data).
     *
     * @return array{0: OrderResource, 1: OrderResource}
     */
    private function orderAsTheProviderReadsIt(Order $order): array
    {
        $read = fn (): OrderResource => $this->getService(ApiResourcePropelTransformerService::class)->modelToResource(
            resourceClass: OrderResource::class,
            propelModel: $this->reread($order),
            context: ['groups' => [OrderResource::GROUP_ADMIN_READ, OrderResource::GROUP_ADMIN_READ_SINGLE, OrderResource::GROUP_ADMIN_WRITE]],
        );

        return [$read(), $read()];
    }

    private function patchThroughTheProcessor(Order $order, OrderResource $resource, OrderResource $previous): void
    {
        $request = Request::create('http://localhost/api/admin/orders/'.$order->getId(), 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $request->attributes->set('id', $order->getId());

        $this->getService(OrderProcessor::class)->process(
            $resource,
            new Patch(uriTemplate: '/admin/orders/{id}', denormalizationContext: ['groups' => [OrderResource::GROUP_ADMIN_WRITE]]),
            ['id' => $order->getId()],
            ['request' => $request, 'previous_data' => $previous],
        );
    }

    /**
     * Commits, from a session of its own, the status another request gave the order since
     * the provider read it.
     */
    private function anotherRequestMovesTheOrderTo(Order $order, string $statusCode): void
    {
        $this->otherSession?->exec(\sprintf(
            'UPDATE `order` SET `status_id` = (SELECT `id` FROM `order_status` WHERE `code` = %s) WHERE `id` = %d',
            $this->otherSession->quote($statusCode),
            (int) $order->getId(),
        ));
        OrderTableMap::clearInstancePool();
    }

    private function storedStatusCodeOf(Order $order): string
    {
        $statement = $this->otherSession?->prepare('SELECT s.`code` FROM `order` o JOIN `order_status` s ON s.`id` = o.`status_id` WHERE o.`id` = ?');
        self::assertNotNull($statement);
        $statement->execute([(int) $order->getId()]);

        return (string) $statement->fetchColumn();
    }

    private function storedDeliveryRefOf(Order $order): ?string
    {
        $statement = $this->otherSession?->prepare('SELECT `delivery_ref` FROM `order` WHERE `id` = ?');
        self::assertNotNull($statement);
        $statement->execute([(int) $order->getId()]);
        $ref = $statement->fetchColumn();

        return false === $ref || null === $ref ? null : (string) $ref;
    }

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    /**
     * Returns once the other process shows a locking statement on the order still running
     * after it was seen running: a statement on one row only lasts that long when it waits
     * on a lock. A session sees the threads of its own account in PROCESSLIST.
     *
     * With $mayEnd, a process that ends without having waited is no failure: it did not
     * have to wait on anything.
     *
     * @param resource $process
     */
    private function waitUntilTheOtherProcessWaitsOnTheOrder($process, bool $mayEnd = false): void
    {
        $ownThreads = [
            (int) Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherSession?->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherSession?->prepare(
            "SELECT CONCAT(ID, ':', INFO) FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND (INFO LIKE '%FOR UPDATE%' OR INFO LIKE 'UPDATE%') AND INFO LIKE '%`order`%' AND ID NOT IN (".implode(',', $ownThreads).')',
        );
        self::assertNotNull($waiting);
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            if (!proc_get_status($process)['running']) {
                if ($mayEnd) {
                    return;
                }

                self::fail('The other process ended before waiting: '.stream_get_contents($this->pipes[(int) $process][2]));
            }

            $waiting->execute();
            $threads = $waiting->fetchAll(\PDO::FETCH_COLUMN);
            $waiting->closeCursor();

            if ([] !== array_intersect($threads, $seen)) {
                return;
            }

            $seen = $threads;
            usleep(200000);
        } while (microtime(true) < $deadline);

        self::fail('The other process never waited on the order.');
    }

    /**
     * @param resource $process
     *
     * @return array{calls: list<string>, releasedPartIds: list<int>, releasedAt: float|null, outcome: string}
     */
    private function waitFor($process): array
    {
        $stdout = $this->pipes[(int) $process][1];
        $stderr = $this->pipes[(int) $process][2];
        unset($this->pipes[(int) $process]);
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'The other process failed: '.$errors.$output);

        // The JSON is the last thing the process prints, after whatever booting it wrote.
        return json_decode(substr((string) $output, (int) strrpos((string) $output, '{"calls"')), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{0: class-string<BaseModule>, 1: string, 2: OrderPaymentPartState, 3: bool, 4: int}> $parts
     */
    private function committedOrderWithParts(string $statusCode, array $parts): Order
    {
        $title = CustomerTitleQuery::create()->findOne() ?? throw new \RuntimeException('No customer title.');
        $country = CountryQuery::create()->findOne() ?? throw new \RuntimeException('No country.');
        $currency = CurrencyQuery::create()->findOne() ?? throw new \RuntimeException('No currency.');
        $lang = LangQuery::create()->findOne() ?? throw new \RuntimeException('No lang.');
        $status = OrderStatusQuery::create()->findOneByCode($statusCode) ?? throw new \RuntimeException('No status '.$statusCode);
        $delivery = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('No delivery module.');

        $customer = (new Customer())
            ->setTitleId($title->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setEmail(\sprintf('release-concurrency-%s@test.com', bin2hex(random_bytes(6))))
            ->setPassword('password');
        $customer->save();
        $this->customerIds[] = (int) $customer->getId();

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken('release-concurrency-'.bin2hex(random_bytes(6)));
        $cart->save();
        $this->cartIds[] = (int) $cart->getId();

        $addressIds = [];

        foreach ([1, 2] as $ignored) {
            $address = (new OrderAddress())
                ->setCustomerTitleId($title->getId())
                ->setFirstname('Jane')
                ->setLastname('Doe')
                ->setAddress1('1 Main Street')
                ->setZipcode('75001')
                ->setCity('Paris')
                ->setCountryId($country->getId());
            $address->save();
            $addressIds[] = $this->orderAddressIds[] = (int) $address->getId();
        }

        $order = (new Order())
            ->setCustomerId($customer->getId())
            ->setInvoiceOrderAddressId($addressIds[0])
            ->setDeliveryOrderAddressId($addressIds[1])
            ->setCurrencyId($currency->getId())
            ->setCurrencyRate(1.0)
            ->setPaymentModuleId($this->modules[BalanceChargingTestPaymentModule::class]->getId())
            ->setDeliveryModuleId($delivery->getId())
            ->setStatusId($status->getId())
            ->setLangId($lang->getId())
            ->setCartId($cart->getId());
        $order->save();
        $this->orderIds[] = (int) $order->getId();

        foreach ($parts as [$moduleClass, $amount, $state, $isPrimary, $position]) {
            (new OrderPaymentPart())
                ->setOrderId($order->getId())
                ->setPaymentModuleId($this->modules[$moduleClass]->getId())
                ->setAmount($amount)
                ->setState($state->value)
                ->setIsPrimary($isPrimary)
                ->setPosition($position)
                ->save();
        }

        return $order;
    }

    /**
     * @param class-string<BaseModule> $moduleClass
     */
    private function committedModule(string $moduleClass): Module
    {
        $module = ModuleQuery::create()->findOneByCode($moduleClass::getModuleCode());

        if (null === $module) {
            $module = (new Module())
                ->setCode($moduleClass::getModuleCode())
                ->setFullNamespace($moduleClass)
                ->setVersion('1.0.0')
                ->setType(BaseModule::PAYMENT_MODULE_TYPE)
                ->setCategory('payment')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save();
            $this->moduleIds[] = (int) $module->getId();
        }

        $instance = new $moduleClass();
        $instance->setContainer(static::getContainer());
        static::getContainer()->set('module.'.$moduleClass::getModuleCode(), $instance);

        return $module;
    }

    /**
     * @return list<string> as another session reads them, in position order
     */
    private function storedStatesOf(Order $order): array
    {
        $statement = $this->otherSession?->prepare('SELECT `state` FROM `order_payment_part` WHERE `order_id` = ? ORDER BY `position`, `id`');
        self::assertNotNull($statement);
        $statement->execute([(int) $order->getId()]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return list<int> in position order
     */
    private function partIdsOf(Order $order): array
    {
        $statement = $this->otherSession?->prepare('SELECT `id` FROM `order_payment_part` WHERE `order_id` = ? ORDER BY `position`, `id`');
        self::assertNotNull($statement);
        $statement->execute([(int) $order->getId()]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function reread(Order $order): Order
    {
        return OrderQuery::create()->findPk($order->getId()) ?? throw new \RuntimeException('The order went away.');
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

    private function deleteFixtures(): void
    {
        $session = $this->otherSession;

        if (null === $session) {
            return;
        }

        foreach ($this->markerFiles as $marker) {
            if (is_file($marker)) {
                unlink($marker);
            }
        }
        $this->markerFiles = [];

        foreach ($this->pipes as $pipes) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        foreach ($this->orderIds as $orderId) {
            $session->exec('DELETE FROM `order_payment_part` WHERE `order_id` = '.$orderId);
            $session->exec('DELETE FROM `order_version` WHERE `id` = '.$orderId);
            $session->exec('DELETE FROM `order` WHERE `id` = '.$orderId);
        }

        foreach ($this->debitNames as $debitName) {
            $session->exec('DELETE FROM `config` WHERE `name` = '.$session->quote($debitName));
        }
        $this->debitNames = [];

        foreach ($this->orderAddressIds as $addressId) {
            $session->exec('DELETE FROM `order_address` WHERE `id` = '.$addressId);
        }

        foreach ($this->cartIds as $cartId) {
            $session->exec('DELETE FROM `cart` WHERE `id` = '.$cartId);
        }

        foreach ($this->customerIds as $customerId) {
            $session->exec('DELETE FROM `customer_version` WHERE `id` = '.$customerId);
            $session->exec('DELETE FROM `customer` WHERE `id` = '.$customerId);
        }

        foreach ($this->moduleIds as $moduleId) {
            $session->exec('DELETE FROM `module_i18n` WHERE `id` = '.$moduleId);
            $session->exec('DELETE FROM `module` WHERE `id` = '.$moduleId);
        }

        $this->orderIds = $this->orderAddressIds = $this->cartIds = $this->customerIds = $this->moduleIds = [];
    }

    /**
     * A second connection to the same database, opened from the configuration Propel
     * holds: the one Propel hands out is the connection under test.
     */
    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(OrderPaymentPartTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(OrderPaymentPartTableMap::DATABASE_NAME),
        );
    }
}
