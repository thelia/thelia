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

namespace Thelia\Tests\Unit\Core\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Thelia\Core\Cache\CacheAdapterFactory;

/**
 * The hosting provider chooses where the application cache is stored with a
 * single environment variable. An empty one keeps the shop on the file system,
 * so an install that sets nothing depends on nothing; a filled one has to fail
 * loudly rather than fall back to the disk, otherwise a shop believes it runs
 * on a shared cache when it does not.
 */
final class CacheAdapterFactoryTest extends TestCase
{
    public function testAnEmptyDataSourceNameKeepsTheCacheOnTheFileSystem(): void
    {
        $adapter = CacheAdapterFactory::create('probe', 0, '', sys_get_temp_dir().'/thelia-cache-factory-test');

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
    }

    public function testBlankSpaceCountsAsNoDataSourceName(): void
    {
        $adapter = CacheAdapterFactory::create('probe', 0, '   ', sys_get_temp_dir().'/thelia-cache-factory-test');

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
    }

    public function testARedisDataSourceNameMovesTheCacheToRedis(): void
    {
        if (!\extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            self::markTestSkipped('Neither the redis extension nor predis/predis is available.');
        }

        $adapter = CacheAdapterFactory::create(
            'probe',
            0,
            'redis://redis:6379?lazy=1',
            sys_get_temp_dir().'/thelia-cache-factory-test',
        );

        self::assertInstanceOf(RedisAdapter::class, $adapter);
    }

    public function testAnUnsupportedSchemeNamesTheVariableAndTheDataSourceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/THELIA_CACHE_DSN/');
        $this->expectExceptionMessageMatches('#sqlite://var/cache.db#');

        CacheAdapterFactory::create('probe', 0, 'sqlite://var/cache.db', sys_get_temp_dir());
    }

    public function testTheErrorNeverRepeatsThePassword(): void
    {
        try {
            CacheAdapterFactory::create('probe', 0, 'unknown://thelia:s3cr3t@example.org:6379', sys_get_temp_dir());
        } catch (\InvalidArgumentException $failure) {
            self::assertStringNotContainsString('s3cr3t', $failure->getMessage());
            self::assertStringContainsString('unknown://thelia:***@example.org:6379', $failure->getMessage());

            return;
        }

        self::fail('An unsupported scheme must be rejected.');
    }
}
