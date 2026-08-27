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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
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
}
