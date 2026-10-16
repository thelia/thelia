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

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Model\Cart;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\TestToken;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Tests\Support\Payment\RefusingStoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\RollingBackStoredMethodsPaymentModule;
use Thelia\Tests\Support\Payment\StoredMethodsPaymentModule;

/**
 * A stored method registered by another request while the erasure of a customer, or
 * the removal of a payment module, is under way: it must be dropped with the others
 * and its provider told, never dropped behind the provider's back.
 *
 * Every other test runs inside the transaction IntegrationTestCase wraps around it,
 * where nothing another session commits is ever at stake. Here the registration is
 * committed for real by a second, genuinely independent session, at the moment the
 * transaction under test has already taken its read view and not yet reached the
 * stored methods: the fixtures are committed too, and cleaned up by hand.
 */
#[Group('concurrency')]
final class CustomerPaymentMethodConcurrencyTest extends IntegrationTestCase
{
    use ForgetsPooledModels;

    protected bool $useTransaction = false;

    private const LOCK_WAIT_TIMEOUT = 2;

    private ?ConnectionInterface $otherSession = null;

    private ?ConnectionInterface $mainConnection = null;

    private ?string $previousLockWaitTimeout = null;

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $moduleIds = [];

    /** @var list<array{string, callable}> */
    private array $listeners = [];

    // The asynchronous session of the deadlock test, closed by tearDown() whatever
    // happened: a transaction it left open would hold the fixtures deleteFixtures() drops.
    private ?\mysqli $competingSession = null;

    private bool $competingStatementPending = false;

    protected function setUp(): void
    {
        parent::setUp();

        StoredMethodsPaymentModule::reset();

        $this->otherSession = $this->openOtherSession();
        $this->otherSession->exec('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_TIMEOUT);

        $this->mainConnection = Propel::getWriteConnection(CustomerPaymentMethodTableMap::DATABASE_NAME);
        $this->previousLockWaitTimeout = (string) $this->mainConnection->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_TIMEOUT);
    }

    protected function tearDown(): void
    {
        try {
            $dispatcher = $this->getService(EventDispatcherInterface::class);

            foreach ($this->listeners as [$eventName, $listener]) {
                $dispatcher->removeListener($eventName, $listener);
            }

            $this->closeTheCompetingSession();

            if ($this->otherSession?->inTransaction()) {
                $this->otherSession->rollBack();
            }

            while ($this->mainConnection?->inTransaction()) {
                $this->mainConnection->rollBack();
            }

            if (null !== $this->previousLockWaitTimeout) {
                $this->mainConnection?->exec('SET SESSION innodb_lock_wait_timeout = '.(int) $this->previousLockWaitTimeout);
            }

            $this->deleteFixtures();
        } finally {
            StoredMethodsPaymentModule::reset();
            self::forgetPooledModels();
            Propel::disableInstancePooling();

            parent::tearDown();
        }
    }

    public function testTheSecondSessionWorksOnTheDatabaseOfThisProcess(): void
    {
        self::assertSame(
            (string) $this->mainConnection?->query('SELECT DATABASE()')->fetchColumn(),
            (string) $this->otherSession?->query('SELECT DATABASE()')->fetchColumn(),
        );

        // Without a TEST_TOKEN there is no suffix to look for: the first assertion is the
        // whole check.
        if ('' !== TestToken::suffix()) {
            self::assertStringEndsWith(TestToken::suffix(), (string) $this->otherSession?->query('SELECT DATABASE()')->fetchColumn());
        }
    }

    public function testAMethodRegisteredDuringTheAnonymizationIsForgottenByItsProvider(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $this->committedCart($customer);
        $before = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_before', 'Visa ending 1111', null);

        // The anonymization reads the orders of the customer first, which takes its read
        // view, then drops the carts: the registration commits right there, before the
        // stored methods are reached.
        $registeredMeanwhile = null;
        $this->onceTheCartIsDropped(function () use ($customer, $module, &$registeredMeanwhile): void {
            $registeredMeanwhile = $this->registerFromTheOtherSession($customer, $module, 'pm_meanwhile');
        });

        $this->getService(EventDispatcherInterface::class)->dispatch(
            new CustomerAnonymizeEvent($customer),
            TheliaEvents::CUSTOMER_ANONYMIZE,
        );

        self::assertNotNull($registeredMeanwhile, 'the competing registration did not run');
        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId())->count());

        $forgotten = StoredMethodsPaymentModule::$forgottenMethodIds;
        sort($forgotten);
        self::assertSame(
            [(int) $before->getId(), $registeredMeanwhile],
            $forgotten,
            'a method dropped by the erasure must reach its provider, the one registered meanwhile included',
        );
    }

    public function testAMethodRegisteredBeforeANestedForgetAllOfIsForgottenByItsProvider(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();

        $connection = $this->mainConnection;
        self::assertNotNull($connection);
        $connection->beginTransaction();

        // A caller that has read before it hands its transaction over: the read view is taken.
        CustomerQuery::create()->findPk($customer->getId(), $connection);

        $registeredMeanwhile = $this->registerFromTheOtherSession($customer, $module, 'pm_meanwhile');

        $tellProvidersToForget = $this->getService(CustomerPaymentMethodService::class)
            ->forgetAllOf($customer, $connection);
        $connection->commit();

        foreach ($tellProvidersToForget as $tellProviderToForget) {
            $tellProviderToForget();
        }

        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertSame([$registeredMeanwhile], StoredMethodsPaymentModule::$forgottenMethodIds);
    }

    public function testAMethodRegisteredDuringTheRemovalOfItsModuleIsForgottenByItsProvider(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $before = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_before', 'Visa ending 1111', null);
        $otherCustomer = $this->committedCustomer();

        $connection = $this->mainConnection;
        self::assertNotNull($connection);
        $connection->beginTransaction();

        // The removal freezes the module on the orders first, which takes the read view.
        ModuleQuery::create()->findPk($module->getId(), $connection);

        $registeredMeanwhile = $this->registerFromTheOtherSession($otherCustomer, $module, 'pm_meanwhile');

        $this->getService(CustomerPaymentMethodService::class)->forgetAllOfModule($module, $connection);
        $connection->commit();

        self::assertSame(0, CustomerPaymentMethodQuery::create()->filterByPaymentModuleId($module->getId())->count());

        $forgotten = StoredMethodsPaymentModule::$forgottenMethodIds;
        sort($forgotten);
        self::assertSame([(int) $before->getId(), $registeredMeanwhile], $forgotten);
    }

    /**
     * A first registration holds its own customer and nothing around it: the first method
     * of the neighbouring customer is written while it has not committed yet, without
     * waiting on it.
     */
    public function testAFirstRegistrationDoesNotHoldBackTheNeighbouringCustomer(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $neighbour = $this->committedCustomer();
        $this->assertNoMethodFrom($customer);

        $neighbourInsertError = null;
        $this->onceAMethodIsAboutToBeInserted(function () use ($neighbour, $module, &$neighbourInsertError): void {
            $session = $this->otherSession;
            self::assertNotNull($session);

            try {
                $session->beginTransaction();
                $session->prepare(
                    'INSERT INTO `customer_payment_method` (`customer_id`, `payment_module_id`, `token`, `label`, `is_default`, `created_at`, `updated_at`)'
                    .' VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                )->execute([$neighbour->getId(), $module->getId(), 'pm_neighbour', 'Visa ending 9999']);
                $session->commit();
            } catch (\PDOException $exception) {
                $session->rollBack();
                $neighbourInsertError = $exception->errorInfo[1] ?? $exception->getCode();
            }
        });

        $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_first', 'Visa ending 1111', null);

        self::assertNull(
            $neighbourInsertError,
            'the neighbouring customer waited on a registration that is not theirs (1205 is a lock wait timeout)',
        );
    }

    /**
     * Two first registrations of two neighbouring customers at the same instant, each
     * past its checks and about to write: both must go through. The competing one runs
     * on a session of its own and waits in the database, not in this process, so the
     * database sees both writes pending at once.
     */
    public function testTwoFirstRegistrationsOfNeighbouringCustomersDoNotDeadlock(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $neighbour = $this->committedCustomer();
        $this->assertNoMethodFrom($customer);

        $competing = $this->competingSession = $this->openAsynchronousSession();
        $competingThreadId = $competing->thread_id;
        $competingResult = null;

        $this->onceAMethodIsAboutToBeInserted(function () use ($competing, $competingThreadId, $neighbour, $module, &$competingResult): void {
            // What a registration that clears the default by an update filtered on its
            // customer sends, a registration nested in a caller's transaction for one:
            // the customer, the default cleared, the row.
            $competing->begin_transaction();
            $competing->query(\sprintf('SELECT `id` FROM `customer` WHERE `id` = %d FOR UPDATE', (int) $neighbour->getId()));
            $competing->query(\sprintf('UPDATE `customer_payment_method` SET `is_default` = 0 WHERE `customer_id` = %d AND `is_default` = 1', (int) $neighbour->getId()));
            $competing->query(
                \sprintf(
                    "INSERT INTO `customer_payment_method` (`customer_id`, `payment_module_id`, `token`, `label`, `is_default`, `created_at`, `updated_at`) VALUES (%d, %d, 'pm_neighbour', 'Visa ending 9999', 1, NOW(), NOW())",
                    (int) $neighbour->getId(),
                    (int) $module->getId(),
                ),
                \MYSQLI_ASYNC,
            );
            $this->competingStatementPending = true;

            // When it does not wait, it is committed before this process writes; once the
            // database shows it waiting on a lock, it is left pending while this process
            // writes.
            $competingResult = $this->reapUnlessWaiting($competing, $competingThreadId);

            if (true === $competingResult) {
                $competing->commit();
            }
        });

        $errorOfThisProcess = null;

        try {
            $this->getService(CustomerPaymentMethodService::class)
                ->register($customer, $module, 'pm_first', 'Visa ending 1111', null);
        } catch (\Throwable $throwable) {
            $errorOfThisProcess = $throwable;

            while (null !== $throwable->getPrevious() && !$throwable instanceof \PDOException) {
                $throwable = $throwable->getPrevious();
            }

            $errorOfThisProcess = $throwable instanceof \PDOException ? ($throwable->errorInfo[1] ?? $throwable->getCode()) : $errorOfThisProcess::class;
        }

        if (null === $competingResult) {
            $competingResult = $this->reap($competing, (float) self::LOCK_WAIT_TIMEOUT + 1);

            true === $competingResult ? $competing->commit() : $competing->rollback();
        }

        $this->closeTheCompetingSession();

        self::assertSame(
            [null, true],
            [$errorOfThisProcess, $competingResult],
            'two first registrations of two different customers ran into each other (1213 is a deadlock)',
        );
    }

    /**
     * The outcome of the pending statement once it is over, or null as soon as the
     * database shows it still running after this process saw it unfinished: an insert of
     * one row only lasts that long when it waits on a lock. INNODB_TRX would say so
     * outright, but it needs the PROCESS privilege; a session sees the threads of its own
     * account in PROCESSLIST without it.
     */
    private function reapUnlessWaiting(\mysqli $session, int $threadId): bool|int|null
    {
        $deadline = microtime(true) + self::LOCK_WAIT_TIMEOUT;
        $waiting = $this->otherSession?->prepare(
            "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = ? AND COMMAND IN ('Query', 'Execute') AND INFO LIKE 'INSERT INTO `customer_payment_method`%'",
        );
        self::assertNotNull($waiting);

        do {
            $outcome = $this->reap($session, 0.0);

            if (null !== $outcome) {
                return $outcome;
            }

            $waiting->execute([$threadId]);
            $isWaiting = (int) $waiting->fetchColumn() > 0;
            $waiting->closeCursor();

            if ($isWaiting) {
                return null;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        self::fail('The competing statement neither ended nor waited on a lock.');
    }

    /**
     * true once the pending statement succeeded, its error number when it failed, null
     * when it is still running after $seconds.
     */
    private function reap(\mysqli $session, float $seconds): bool|int|null
    {
        $deadline = microtime(true) + $seconds;

        do {
            $read = $errors = $rejected = [$session];

            if (mysqli_poll($read, $errors, $rejected, 0, 10000) > 0) {
                $this->competingStatementPending = false;

                try {
                    $result = $session->reap_async_query();
                } catch (\mysqli_sql_exception $exception) {
                    return $exception->getCode();
                }

                return false === $result ? $session->errno : true;
            }
        } while (microtime(true) < $deadline);

        return null;
    }

    private function closeTheCompetingSession(): void
    {
        $session = $this->competingSession;

        if (null === $session) {
            return;
        }

        $this->competingSession = null;

        try {
            if ($this->competingStatementPending) {
                $this->reap($session, (float) self::LOCK_WAIT_TIMEOUT + 1);
            }

            $session->rollback();
        } catch (\Throwable) {
            // Closing the session ends its transaction anyway.
        } finally {
            $this->competingStatementPending = false;
            $session->close();
        }
    }

    private function openAsynchronousSession(): \mysqli
    {
        // PDO cannot leave a statement waiting on a lock and come back to it: only mysqli
        // can, and nothing else in the suite needs that extension.
        if (!\extension_loaded('mysqli')) {
            self::markTestSkipped('The asynchronous session needs the mysqli extension.');
        }

        $configuration = Propel::getServiceContainer()
            ->getConnectionManager(CustomerPaymentMethodTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionManagerSingle::class, $configuration);
        $configuration = $configuration->getConfiguration();

        preg_match_all('/(\w+)=([^;]*)/', substr((string) $configuration['dsn'], \strlen('mysql:')), $matches, \PREG_SET_ORDER);
        $dsn = [];

        foreach ($matches as [, $key, $value]) {
            $dsn[$key] = $value;
        }

        $session = new \mysqli(
            $dsn['host'] ?? 'localhost',
            (string) $configuration['user'],
            (string) $configuration['password'],
            $dsn['dbname'] ?? '',
            (int) ($dsn['port'] ?? 3306),
        );
        $session->query('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_TIMEOUT);

        return $session;
    }

    private function onceAMethodIsAboutToBeInserted(callable $then): void
    {
        $ran = false;
        $listener = static function () use (&$ran, $then): void {
            if ($ran) {
                return;
            }

            $ran = true;
            $then();
        };

        $this->getService(EventDispatcherInterface::class)->addListener('propel.pre.insert.customer_payment_method', $listener);
        $this->listeners[] = ['propel.pre.insert.customer_payment_method', $listener];
    }

    /**
     * The two customers of a test are the most recent ones: no method stands between
     * them in the customer_id index, which is the gap both registrations write in.
     */
    private function assertNoMethodFrom(Customer $customer): void
    {
        self::assertSame(
            0,
            CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId(), Criteria::GREATER_EQUAL)->count(),
        );
    }

    /**
     * The removal of a module drops the default method of a customer who also stored one
     * through another module, registered after the removal took its read view: that one
     * becomes the default, rather than leaving the customer without any.
     */
    public function testRemovingAModulePromotesAMethodRegisteredThroughAnotherModuleMeanwhile(): void
    {
        $removedModule = $this->committedModule();
        $otherModule = $this->committedModule(RefusingStoredMethodsPaymentModule::class);
        $customer = $this->committedCustomer();
        $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $removedModule, 'pm_removed', 'Visa ending 1111', null);

        $connection = $this->mainConnection;
        self::assertNotNull($connection);
        $connection->beginTransaction();

        // Action\Module::delete() reads the module and freezes it on the orders first.
        ModuleQuery::create()->findPk($removedModule->getId(), $connection);

        $registeredMeanwhile = $this->registerFromTheOtherSession($customer, $otherModule, 'pm_other');

        $this->getService(CustomerPaymentMethodService::class)->forgetAllOfModule($removedModule, $connection);
        $connection->commit();

        self::assertSame([$registeredMeanwhile], $this->defaultMethodIdsOf($customer));
    }

    /**
     * A provider that rolls back the connection when told to forget a method loses what the
     * transaction of the removal held, writes and locks: the guard of the module lifecycle
     * restores the level but cannot give that back, so the removal fails and the rollback
     * of its caller leaves the database as it was.
     */
    public function testAProviderRollingBackWhenForgettingMakesTheRemovalFail(): void
    {
        $module = $this->committedModule(RollingBackStoredMethodsPaymentModule::class);
        $customer = $this->committedCustomer();
        $method = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_rolled_back', 'Visa ending 1111', null);

        $connection = $this->mainConnection;
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->beginTransaction();

        // What the removal wrote before reaching the provider, its freeze of the module.
        $connection->exec("UPDATE `module` SET `version` = '9.9.9' WHERE `id` = ".(int) $module->getId());

        try {
            $this->getService(CustomerPaymentMethodService::class)->forgetAllOfModule($module, $connection);
            self::fail('The removal must fail when the provider ended its transaction.');
        } catch (\LogicException $logicException) {
            self::assertStringContainsString('#'.$method->getId(), $logicException->getMessage());
            self::assertStringNotContainsString('pm_rolled_back', $logicException->getMessage());
        } finally {
            while ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        self::assertSame(0, $connection->getNestedTransactionCount());
        self::assertSame(1, CustomerPaymentMethodQuery::create()->filterByPaymentModuleId($module->getId())->count());
        self::assertSame('1.0.0', (string) $connection->query('SELECT `version` FROM `module` WHERE `id` = '.(int) $module->getId())->fetchColumn());
    }

    /**
     * A notification handler that opened its own transaction and read before registering:
     * the same token, committed meanwhile by the return URL, is refreshed rather than
     * stored twice, and stays the only default.
     */
    public function testARegistrationNestedAfterAReadRefreshesTheTokenCommittedMeanwhile(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();

        $connection = $this->mainConnection;
        self::assertNotNull($connection);
        $connection->beginTransaction();

        CustomerQuery::create()->findPk($customer->getId(), $connection);

        $registeredMeanwhile = $this->registerFromTheOtherSession($customer, $module, 'pm_same', true);

        $registered = $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_same', 'Visa ending 1111', null);
        $connection->commit();

        self::assertSame($registeredMeanwhile, (int) $registered->getId(), 'the token committed meanwhile was stored a second time');
        self::assertSame(1, CustomerPaymentMethodQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertSame([$registeredMeanwhile], $this->defaultMethodIdsOf($customer));
    }

    /**
     * In production the instance pool is on: the row a request loaded before calling the
     * service, the API item provider for instance, is what a plain re-read hands back. A
     * default moved by another request since must not be read from that copy.
     */
    public function testSettingTheDefaultOverAPooledCopyLeavesExactlyOneDefault(): void
    {
        [$customer, $first] = $this->twoMethodsWithTheFirstPooledAsTheDefault();
        $pooledFirst = CustomerPaymentMethodQuery::create()->findPk($first);
        self::assertNotNull($pooledFirst);

        $this->getService(CustomerPaymentMethodService::class)->setDefault($customer, $pooledFirst);

        self::assertSame([$first], $this->defaultMethodIdsOf($customer));
    }

    public function testForgettingOverAPooledCopyLeavesExactlyOneDefault(): void
    {
        [$customer, $first, $second] = $this->twoMethodsWithTheFirstPooledAsTheDefault();
        $pooledSecond = CustomerPaymentMethodQuery::create()->findPk($second);
        self::assertNotNull($pooledSecond);

        // The second one, pooled as not the default and made the default meanwhile.
        $this->getService(CustomerPaymentMethodService::class)->forget($customer, $pooledSecond);

        self::assertSame([$first], $this->defaultMethodIdsOf($customer));
    }

    public function testRegisteringAPooledTokenAsTheDefaultLeavesExactlyOneDefault(): void
    {
        [$customer, $first] = $this->twoMethodsWithTheFirstPooledAsTheDefault();
        $module = $this->committedModule();

        $this->getService(CustomerPaymentMethodService::class)
            ->register($customer, $module, 'pm_first', 'Visa ending 1111', null, true);

        self::assertSame([$first], $this->defaultMethodIdsOf($customer));
    }

    /**
     * The default cleared by a write the pool does not see would stay the default in the
     * copy the pool keeps, which is what a listing in the same request reads.
     */
    public function testWhatTheSameRequestListsAfterANewDefaultHoldsOneDefault(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $service = $this->getService(CustomerPaymentMethodService::class);

        Propel::enableInstancePooling();
        self::forgetPooledModels();

        $service->register($customer, $module, 'pm_first', 'Visa ending 1111', null);
        $second = $service->register($customer, $module, 'pm_second', 'Visa ending 2222', null, true);

        $listedAsTheDefault = array_values(array_map(
            static fn (CustomerPaymentMethod $method): int => (int) $method->getId(),
            array_filter($service->listFor($customer), static fn (CustomerPaymentMethod $method): bool => true === $method->getIsDefault()),
        ));

        self::assertSame([(int) $second->getId()], $listedAsTheDefault);
    }

    /**
     * Two methods, both pooled, the first one as the default, then the default moved to
     * the second one by another session.
     *
     * @return array{Customer, int, int}
     */
    private function twoMethodsWithTheFirstPooledAsTheDefault(): array
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $service = $this->getService(CustomerPaymentMethodService::class);
        $first = (int) $service->register($customer, $module, 'pm_first', 'Visa ending 1111', null)->getId();
        $second = (int) $service->register($customer, $module, 'pm_second', 'Visa ending 2222', null)->getId();

        Propel::enableInstancePooling();
        self::forgetPooledModels();
        self::assertTrue(true === CustomerPaymentMethodQuery::create()->findPk($first)?->getIsDefault());
        self::assertTrue(false === CustomerPaymentMethodQuery::create()->findPk($second)?->getIsDefault());

        $this->otherSessionMakesTheDefault($customer, $second);

        return [$customer, $first, $second];
    }

    private function otherSessionMakesTheDefault(Customer $customer, int $methodId): void
    {
        $session = $this->otherSession;
        self::assertNotNull($session);

        $session->beginTransaction();
        $session->prepare('SELECT `id` FROM `customer` WHERE `id` = ? FOR UPDATE')->execute([$customer->getId()]);
        $session->prepare('UPDATE `customer_payment_method` SET `is_default` = (`id` = ?) WHERE `customer_id` = ?')
            ->execute([$methodId, $customer->getId()]);
        $session->commit();
    }

    public function testASecondMethodRegisteredAsTheDefaultIsTheOnlyDefault(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $service->register($customer, $module, 'pm_first', 'Visa ending 1111', null);
        $second = $service->register($customer, $module, 'pm_second', 'Visa ending 2222', null, true);

        self::assertSame([(int) $second->getId()], $this->defaultMethodIdsOf($customer));
    }

    public function testSettingTheDefaultLeavesItTheOnlyDefault(): void
    {
        $module = $this->committedModule();
        $customer = $this->committedCustomer();
        $service = $this->getService(CustomerPaymentMethodService::class);

        $first = $service->register($customer, $module, 'pm_first', 'Visa ending 1111', null);
        $service->register($customer, $module, 'pm_second', 'Visa ending 2222', null, true);
        $service->setDefault($customer, $first);

        self::assertSame([(int) $first->getId()], $this->defaultMethodIdsOf($customer));
    }

    /**
     * @return list<int>
     */
    private function defaultMethodIdsOf(Customer $customer): array
    {
        return array_map(
            intval(...),
            CustomerPaymentMethodQuery::create()
                ->filterByCustomerId($customer->getId())
                ->filterByIsDefault(true)
                ->select('Id')
                ->find()
                ->toArray(),
        );
    }

    /**
     * What register() does, on the other session: the customer locked first, the row
     * inserted, committed.
     */
    private function registerFromTheOtherSession(Customer $customer, Module $module, string $token, bool $default = false): int
    {
        $session = $this->otherSession;
        self::assertNotNull($session);

        $session->beginTransaction();
        $lock = $session->prepare('SELECT `id` FROM `customer` WHERE `id` = ? FOR UPDATE');
        $lock->execute([$customer->getId()]);
        $insert = $session->prepare(
            'INSERT INTO `customer_payment_method` (`customer_id`, `payment_module_id`, `token`, `label`, `is_default`, `created_at`, `updated_at`)'
            .' VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
        );
        $insert->execute([$customer->getId(), $module->getId(), $token, 'Visa ending 9999', $default ? 1 : 0]);
        $id = (int) $session->lastInsertId();
        $session->commit();

        return $id;
    }

    private function onceTheCartIsDropped(callable $then): void
    {
        $ran = false;
        $listener = static function () use (&$ran, $then): void {
            if ($ran) {
                return;
            }

            $ran = true;
            $then();
        };

        $this->getService(EventDispatcherInterface::class)->addListener('propel.pre.delete.cart', $listener);
        $this->listeners[] = ['propel.pre.delete.cart', $listener];
    }

    /**
     * @param class-string<BaseModule> $moduleClass
     */
    private function committedModule(string $moduleClass = StoredMethodsPaymentModule::class): Module
    {
        $code = $moduleClass::getModuleCode();
        $existing = ModuleQuery::create()->findOneByCode($code);

        if (null !== $existing) {
            return $existing;
        }

        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace($moduleClass)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save();
        $this->moduleIds[] = (int) $module->getId();

        return $module;
    }

    private function committedCustomer(): Customer
    {
        $title = CustomerTitleQuery::create()->findOne();
        self::assertNotNull($title, 'the test database holds no customer title');

        $customer = (new Customer())
            ->setTitleId($title->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setEmail(\sprintf('concurrency-%s@test.com', bin2hex(random_bytes(6))))
            ->setPassword('password');
        $customer->save();
        $this->customerIds[] = (int) $customer->getId();

        return $customer;
    }

    private function committedCart(Customer $customer): Cart
    {
        $currency = CurrencyQuery::create()->findOne();
        self::assertNotNull($currency, 'the test database holds no currency');

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken('concurrency-'.bin2hex(random_bytes(6)));
        $cart->save();

        return $cart;
    }

    private function deleteFixtures(): void
    {
        $session = $this->otherSession;

        if (null === $session) {
            return;
        }

        foreach ($this->customerIds as $customerId) {
            $session->exec('DELETE FROM `customer_payment_method` WHERE `customer_id` = '.$customerId);
            $session->exec('DELETE FROM `cart` WHERE `customer_id` = '.$customerId);
            $session->exec('DELETE FROM `customer_version` WHERE `id` = '.$customerId);
            $session->exec('DELETE FROM `customer` WHERE `id` = '.$customerId);
        }

        foreach ($this->moduleIds as $moduleId) {
            $session->exec('DELETE FROM `customer_payment_method` WHERE `payment_module_id` = '.$moduleId);
            $session->exec('DELETE FROM `module_i18n` WHERE `id` = '.$moduleId);
            $session->exec('DELETE FROM `module` WHERE `id` = '.$moduleId);
        }

        $this->customerIds = [];
        $this->moduleIds = [];
    }

    /**
     * A second connection to the same database, opened from the configuration Propel
     * holds: the one Propel hands out is the connection under test.
     */
    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(CustomerPaymentMethodTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(CustomerPaymentMethodTableMap::DATABASE_NAME),
        );
    }
}
