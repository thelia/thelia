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

namespace Thelia\Tests\Integration\Core\Cache;

use PHPUnit\Framework\Attributes\Test;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The cache entry outlives the process that wrote it and is read back by every
 * other process of the shop, whatever environment they were started with. A
 * configuration name overridden by an environment variable must therefore never
 * reach it: the override applies where it is declared, and nowhere else.
 */
final class ConfigCacheServiceTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private const CONFIG_NAME = 'test-config-cache-env-override';
    private const ENV_NAME = 'TEST_CONFIG_CACHE_ENV_OVERRIDE';

    protected function tearDown(): void
    {
        unset($_ENV[self::ENV_NAME]);

        // The rolled back transaction leaves the entry describing rows that
        // are gone, so it goes with the test.
        $this->sharedEntryPool()->deleteItem(ConfigCacheService::CACHE_KEY);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAProcessWithAnEnvironmentOverrideDoesNotPublishItToTheOthers(): void
    {
        ConfigQuery::write(self::CONFIG_NAME, 'from-database');

        // A single pool object stands for the entry the processes share.
        $sharedPool = new ArrayAdapter();
        $configCacheService = new ConfigCacheService($sharedPool);

        // First process: started with the variable set, it warms the shared entry.
        $_ENV[self::ENV_NAME] = 'from-environment';
        ConfigQuery::resetCache();
        $configCacheService->initCacheConfigs();

        self::assertSame(
            'from-environment',
            ConfigQuery::read(self::CONFIG_NAME),
            'The process that declares the variable must read it.',
        );

        $snapshot = $sharedPool->getItem(ConfigCacheService::CACHE_KEY)->get();

        self::assertSame(
            'from-database',
            $snapshot[self::CONFIG_NAME],
            'The shared entry must hold the stored value, not the override.',
        );

        // Second process: same shop, same cache, no variable set.
        unset($_ENV[self::ENV_NAME]);
        ConfigQuery::resetCache();
        $configCacheService->initCacheConfigs();

        self::assertSame(
            'from-database',
            ConfigQuery::read(self::CONFIG_NAME),
            'A process without the variable must not read the value of one that had it.',
        );
    }

    public function testReadingAnOverriddenNameTwiceStillCostsNoQuery(): void
    {
        ConfigQuery::write(self::CONFIG_NAME, 'from-database');
        ConfigQuery::resetCache();

        $_ENV[self::ENV_NAME] = 'from-environment';

        $statements = $this->recordSqlQueries(static function (): void {
            self::assertSame('from-environment', ConfigQuery::read(self::CONFIG_NAME));
            self::assertSame('from-environment', ConfigQuery::read(self::CONFIG_NAME));
            self::assertSame('from-environment', ConfigQuery::read(self::CONFIG_NAME));
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'Applying the override on read must not cost a query.',
        );
    }

    #[Test]
    public function theSharedEntryIsHandedOverBeforeThereIsAContainer(): void
    {
        $this->getService(ConfigCacheService::class)->initCacheConfigs();
        ConfigQuery::resetCache();

        $statements = $this->recordSqlQueries(static function (): void {
            ConfigCacheService::warmFromSharedEntry(static::$kernel->getCacheDir());

            ConfigQuery::read('store_name');
        });

        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'Reading the configuration before the container is built must not reach the database.',
        );
    }

    #[Test]
    public function nothingIsHandedOverWhenThereIsNoSharedEntry(): void
    {
        $this->sharedEntryPool()->deleteItem(ConfigCacheService::CACHE_KEY);
        ConfigQuery::resetCache();

        $statements = $this->recordSqlQueries(static function (): void {
            ConfigCacheService::warmFromSharedEntry(static::$kernel->getCacheDir());

            ConfigQuery::read('store_name');
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'With no entry to hand over, the table is read as it always was.',
        );
    }

    #[Test]
    public function writingAConfigurationValueDropsTheSharedEntry(): void
    {
        $this->getService(ConfigCacheService::class)->initCacheConfigs();

        self::assertTrue(
            $this->sharedEntryPool()->getItem(ConfigCacheService::CACHE_KEY)->isHit(),
            'sanity: the entry has to be there before a write can drop it',
        );

        ConfigQuery::write(self::CONFIG_NAME, 'written');

        self::assertFalse(
            $this->sharedEntryPool()->getItem(ConfigCacheService::CACHE_KEY)->isHit(),
            'A write has to drop the entry: it carries no expiry of its own, so nothing else would ever refresh it.',
        );
        self::assertSame('written', ConfigQuery::read(self::CONFIG_NAME));
    }

    /**
     * A pool object of its own, on the same files: an adapter keeps what it
     * has read in memory, and these assertions are about what is on disk.
     */
    private function sharedEntryPool(): CacheItemPoolInterface
    {
        return new FilesystemAdapter(
            ConfigCacheService::CACHE_NAMESPACE,
            0,
            static::$kernel->getCacheDir(),
        );
    }
}
