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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\EventListener\ModelConfigListener;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The catalog state is what lets a shop without a bundle pay nothing for them: a variable of the
 * configuration, kept by the model of the product whoever saves or deletes it.
 */
final class BundleCatalogStateTest extends IntegrationTestCase
{
    use BuildsBundles;

    public function testAShopWithoutABundleHasNone(): void
    {
        $state = new BundleCatalogState();

        self::assertFalse($state->hasBundles());
        self::assertFalse($state->hasComputedBundles());
    }

    public function testAFixedBundleIsABundleThatNothingPricesByItsComponents(): void
    {
        $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class), BundlePriceMode::Fixed);
        $state = new BundleCatalogState();

        self::assertTrue($state->hasBundles());
        self::assertFalse($state->hasComputedBundles());
        self::assertSame('1', ConfigQuery::read(BundleCatalogState::CONFIG_KEY));
    }

    public function testAComputedBundleIsKnownTheMomentItIsSaved(): void
    {
        $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $state = new BundleCatalogState();

        self::assertTrue($state->hasBundles());
        self::assertTrue($state->hasComputedBundles());
        self::assertSame('2', ConfigQuery::read(BundleCatalogState::CONFIG_KEY));
    }

    public function testChangingThePriceModeMovesTheState(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $bundle = $this->bundleProduct($this->createFixtureFactory(), $service, BundlePriceMode::Fixed);

        $service->setBundleSettings((int) $bundle->getId(), true, BundlePriceMode::Computed, '0', BundleDiscountType::Percent);
        self::assertTrue((new BundleCatalogState())->hasComputedBundles());

        $service->setBundleSettings((int) $bundle->getId(), true, BundlePriceMode::Fixed, '0', BundleDiscountType::Percent);
        self::assertTrue((new BundleCatalogState())->hasBundles());
        self::assertFalse((new BundleCatalogState())->hasComputedBundles());
    }

    public function testAProductThatStopsBeingABundleLeavesTheShopWithNone(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $bundle = $this->bundleProduct($this->createFixtureFactory(), $service);

        $service->setBundleSettings((int) $bundle->getId(), false, null, null, null);

        self::assertFalse((new BundleCatalogState())->hasBundles());
        self::assertSame('0', ConfigQuery::read(BundleCatalogState::CONFIG_KEY));
    }

    public function testDeletingTheLastBundleLeavesTheShopWithNone(): void
    {
        $other = $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        self::assertTrue((new BundleCatalogState())->hasBundles());

        $other->delete();

        self::assertFalse((new BundleCatalogState())->hasBundles());
    }

    public function testSavingAProductThatIsNotABundleCountsNothing(): void
    {
        $product = $this->component($this->createFixtureFactory(), '10', 1);
        $connection = Propel::getConnection('TheliaMain');
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        $product->setVisible(0)->save();
        $queries = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        $counts = $connection->getQueryCount();
        self::assertGreaterThan(0, $queries, 'The save itself writes.');
        self::assertFalse((new BundleCatalogState())->hasBundles());
        self::assertSame($counts, $connection->getQueryCount());
    }

    public function testTheStateIsReadWithoutAQuery(): void
    {
        $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $state = new BundleCatalogState();
        $state->hasBundles();
        $connection = Propel::getConnection('TheliaMain');
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        $state->hasBundles();
        $state->hasComputedBundles();
        $queries = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        self::assertSame(0, $queries);
    }

    public function testADatabaseWithoutTheVariableHasNoBundle(): void
    {
        $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        ConfigQuery::create()->filterByName(BundleCatalogState::CONFIG_KEY)->delete();
        ConfigQuery::resetCache();

        self::assertFalse((new BundleCatalogState())->hasBundles());
    }

    public function testAStoredZeroIsAValueNotAnAbsence(): void
    {
        ConfigQuery::write(BundleCatalogState::CONFIG_KEY, '0');

        self::assertFalse((new BundleCatalogState())->hasBundles());

        (new BundleCatalogState())->refresh();
        self::assertSame('0', ConfigQuery::read(BundleCatalogState::CONFIG_KEY));
    }

    /**
     * The variable is written, and the shared entry of the configuration dropped, in the transaction
     * of the product that becomes the first bundle: a request served before the commit rebuilds the
     * entry from what it can see, the shop without a bundle. The end of the writing request, after its
     * commit, drops that entry again, so it is not kept until the next write of the configuration.
     */
    public function testAConfigurationEntryRebuiltBeforeTheCommitOfTheFirstBundleIsDroppedWhenTheRequestEnds(): void
    {
        $configCache = $this->getService(ConfigCacheService::class);
        $sharedPool = (fn (): AdapterInterface => $this->cache)->call($configCache);

        try {
            $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
            self::assertTrue((new BundleCatalogState())->hasComputedBundles(), 'The writing request reads its own write.');

            // Another request, on its own connection, rebuilds the shared entry before the commit.
            $seenByAnotherRequest = $this->anotherConnection()
                ->query('SELECT `name`, `value` FROM `config`')
                ->fetchAll(\PDO::FETCH_KEY_PAIR);
            self::assertNotSame('2', $seenByAnotherRequest[BundleCatalogState::CONFIG_KEY] ?? null, 'What is not committed is not seen.');
            $sharedPool->get(ConfigCacheService::CACHE_KEY, static fn (): array => $seenByAnotherRequest);

            $dispatcher = $this->getContainer()->get('event_dispatcher');
            $listener = $this->getService(ModelConfigListener::class);
            $envelope = new Envelope(new \stdClass());
            $endsOfTheRequest = [
                KernelEvents::TERMINATE => new \stdClass(),
                ConsoleEvents::TERMINATE => new \stdClass(),
                WorkerMessageHandledEvent::class => new WorkerMessageHandledEvent($envelope, 'async'),
                WorkerMessageFailedEvent::class => new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('job failed')),
            ];

            foreach ($endsOfTheRequest as $eventName => $event) {
                self::assertContains([$listener, 'resetCacheAfterTheRequest'], $dispatcher->getListeners($eventName), $eventName);
            }

            // The end of a job of the worker, handled or failed, is the end of its request as well.
            foreach ([WorkerMessageHandledEvent::class, WorkerMessageFailedEvent::class] as $eventName) {
                $listener->resetCache();
                $sharedPool->get(ConfigCacheService::CACHE_KEY, static fn (): array => $seenByAnotherRequest);
                self::assertTrue($sharedPool->getItem(ConfigCacheService::CACHE_KEY)->isHit());

                // Only the listener of the configuration is run: the other listeners of the worker events handle the envelope.
                foreach ($dispatcher->getListeners($eventName) as $registered) {
                    if (\is_array($registered) && $registered[0] === $listener) {
                        $registered($endsOfTheRequest[$eventName], $eventName, $dispatcher);
                    }
                }

                self::assertFalse($sharedPool->getItem(ConfigCacheService::CACHE_KEY)->isHit(), $eventName.': the entry built on the state before the commit is gone.');
            }

            $listener->resetCache();
            $sharedPool->get(ConfigCacheService::CACHE_KEY, static fn (): array => $seenByAnotherRequest);
            $listener->resetCacheAfterTheRequest();

            self::assertFalse($sharedPool->getItem(ConfigCacheService::CACHE_KEY)->isHit(), 'The entry built on the state before the commit is gone.');
            $configCache->initCacheConfigs();
            self::assertTrue((new BundleCatalogState())->hasComputedBundles());
        } finally {
            $sharedPool->delete(ConfigCacheService::CACHE_KEY);
            ConfigQuery::resetCache();
        }
    }

    public function testRefreshingRecountsTheCatalog(): void
    {
        $this->bundleProduct($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        ConfigQuery::write(BundleCatalogState::CONFIG_KEY, '0');
        self::assertFalse((new BundleCatalogState())->hasBundles());

        (new BundleCatalogState())->refresh();

        self::assertTrue((new BundleCatalogState())->hasComputedBundles());
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds: Propel hands out
     * one connection per datasource, and its transaction would be the one of the test.
     */
    private function anotherConnection(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ConfigTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create($connectionManager->getConfiguration(), $serviceContainer->getAdapter(ConfigTableMap::DATABASE_NAME));
    }
}
