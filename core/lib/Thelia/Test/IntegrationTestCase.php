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

namespace Thelia\Test;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\DependencyInjection\Compiler\TestPublicServicesPass;
use Thelia\Core\Hook\ModuleHookConfigurationStore;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\TheliaKernel;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\TaxRuleQuery;

/**
 * Base class for Thelia integration tests.
 *
 * Boots the kernel, initializes singletons (Translator, URL), pushes a
 * minimal Request, and wraps each test in a transaction rolled back in
 * tearDown() for full isolation.
 *
 * Prerequisites (run before the test suite):
 *   php bin/test-prepare
 *
 * Constraints:
 *   - Auto-increment values are NOT rolled back: never hardcode IDs.
 *   - The kernel is static across tests: do not mutate the container.
 *   - DDL/TRUNCATE tests must set $useTransaction = false and clean up manually.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    /**
     * Each test opens its transaction under a hundred nested levels. Propel only
     * counts a nested level, it sends nothing to the database: code under test
     * that commits a transaction it did not open (a commit() left without its
     * beginTransaction()) takes the counter down instead of committing the rows
     * of the test, which would then stay in the database for every test after
     * it. tearDown() still rolls everything back, and the test fails saying so.
     * One level would not be enough: such code is often called more than once
     * in a test.
     */
    protected const int TEST_TRANSACTION_LEVELS = 100;

    protected bool $useTransaction = true;

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        self::bootKernel();

        if (!TheliaKernel::isInstalled()) {
            $this->markTestSkipped(
                'Test database not available. Run: php bin/test-prepare',
            );
        }

        $container = static::getContainer();

        // Initialize singletons that business code accesses statically
        try {
            Translator::getInstance();
        } catch (\RuntimeException) {
            $container->get('thelia.translator');
        }

        try {
            \Thelia\Tools\URL::getInstance();
        } catch (\RuntimeException) {
            $container->get('thelia.url.manager');
        }

        // Push a minimal Request so that listeners accessing the request stack
        // don't crash on null. The request also needs a session: several Action
        // listeners call $request->getSession() unconditionally, which throws a
        // SessionNotFoundException on a bare Request.
        $requestStack = $container->get('request_stack');
        if (null === $requestStack->getCurrentRequest()) {
            $request = Request::create('http://localhost');
            // Thelia Action listeners type-hint the concrete
            // `Thelia\Core\HttpFoundation\Session\Session` class, so
            // the base Symfony Session is not enough.
            $request->setSession(new Session(new MockArraySessionStorage()));
            $requestStack->push($request);
        }

        if ($this->useTransaction) {
            $this->connection = Propel::getConnection('TheliaMain');

            for ($level = 0; $level < self::TEST_TRANSACTION_LEVELS; ++$level) {
                $this->connection->beginTransaction();
            }
        }

        // Propel caches ActiveRecord instances in a per-class in-memory
        // pool. Across tests this cache survives the transaction rollback
        // and leaks "ghost" rows that no longer exist on disk, breaking
        // isolation. Turning pooling off guarantees every read goes back
        // to the DB, which is exactly what we want under test.
        Propel::disableInstancePooling();
    }

    protected function tearDown(): void
    {
        $closedByTheCodeUnderTest = $this->useTransaction
            && $this->connection instanceof ConnectionWrapper
            && $this->connection->getNestedTransactionCount() < self::TEST_TRANSACTION_LEVELS;

        if ($this->useTransaction && $this->connection instanceof ConnectionInterface && $this->connection->inTransaction()) {
            // A test that fails while application code is inside its own
            // Propel "nested transaction" leaves the nesting counter above 1.
            // A plain rollBack() would only decrement it, keeping the real
            // transaction (and its metadata locks) open for the rest of the
            // PHPUnit process — on MySQL the next DDL then waits on the
            // schema lock until lock_wait_timeout (~1 year).
            $this->connection instanceof ConnectionWrapper
                ? $this->connection->forceRollBack()
                : $this->connection->rollBack();
        }

        $this->connection = null;

        $this->forgetWhatTheTestLeftInMemory();

        parent::tearDown();

        if ($closedByTheCodeUnderTest) {
            self::fail('The code under test committed or rolled back a database transaction it did not open.');
        }
    }

    /**
     * The core memoises rows for the length of a request: the default country,
     * currency and language, the tax rules of a product, the order statuses, the
     * shop configuration. A test is one request, and its rows are rolled back
     * with it, so the next test would read rows that no longer exist, or ids the
     * database hands out again to other rows: the cart of a session without one
     * was the cart a previous test had left there, with a currency rolled back since. The
     * configuration is also kept in a cache shared between processes, which a
     * test can fill with values it never committed.
     */
    private function forgetWhatTheTestLeftInMemory(): void
    {
        if (static::$booted && static::getContainer()->has(ConfigCacheService::class)) {
            static::getContainer()->get(ConfigCacheService::class)->initCacheConfigs(true);
        }

        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();
        Lang::resetActiveLangsCache();
        ModuleQuery::resetActivated();
        ModuleConfigQuery::resetConfigCache();
        OrderReturnStatusQuery::resetCache();

        foreach ([
            [Currency::class, 'defaultCurrency', null],
            [Lang::class, 'defaultLanguage', null],
            [TaxRuleQuery::class, 'caches', []],
            [OrderStatusQuery::class, 'statusIdListsCache', []],
            [OrderStatusQuery::class, 'statusModelCache', []],
            [ModuleHookConfigurationStore::class, 'configuration', null],
            [Session::class, 'transientCart', null],
        ] as [$class, $property, $emptyValue]) {
            (new \ReflectionProperty($class, $property))->setValue(null, $emptyValue);
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function getService(string $id): object
    {
        $container = static::getContainer();

        // Symfony drops a private service nothing else references, and the
        // message it then answers with ("removed or inlined when the container
        // was compiled") reads like a broken environment. It is not: the
        // service simply has no declared way in. Say so, and say what to do.
        if (!$container->has($id)) {
            throw new \LogicException(\sprintf('The "%s" service cannot be fetched from the test container: it is private and nothing in the compiled container references it. Make it public for the test environment (see %s) or reach it through a service that is.', $id, TestPublicServicesPass::class));
        }

        return $container->get($id);
    }

    protected function getPropelConnection(): ConnectionInterface
    {
        return Propel::getConnection('TheliaMain');
    }

    protected function createFixtureFactory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}
