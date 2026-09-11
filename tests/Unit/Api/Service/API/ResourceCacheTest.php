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

namespace Thelia\Tests\Unit\Api\Service\API;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Domain\Sale\SaleAudienceChecker;

/**
 * The cross-request cache of the data access layer keys on the path, the format
 * and the locale — never on who is asking. Only paths whose answer is the same
 * for everybody are eligible.
 *
 * A running reserved operation breaks that promise for the catalog: two visitors
 * asking for the same product are owed two different prices. The catalog paths
 * are therefore bypassed for as long as such an operation runs, and cached again
 * as soon as none does.
 */
final class ResourceCacheTest extends TestCase
{
    private const CATALOG_PATH = '/api/front/products/1';
    private const NEUTRAL_PATH = '/api/front/countries';

    public function testTheCatalogIsCachedWhenNoReservedOperationRuns(): void
    {
        $cache = $this->cache(hasActiveReservedSale: false);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(
            ['first'],
            $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']),
            'The second read is answered from the cache, so the payload is the first one.',
        );
    }

    public function testTheCatalogIsBypassedWhileAReservedOperationRuns(): void
    {
        $cache = $this->cache(hasActiveReservedSale: true);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(
            ['second'],
            $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']),
            'A reserved price would be served to whoever asked next: the catalog is not cached at all.',
        );
    }

    /**
     * The bypass is aimed at the two paths a price travels on. Everything else the
     * allow list holds answers the same thing to everybody, reserved operation or
     * not, and keeps its cache.
     */
    public function testTheOtherCachedPathsAreUntouchedByAReservedOperation(): void
    {
        $cache = $this->cache(hasActiveReservedSale: true);

        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['first']));
        self::assertSame(
            ['first'],
            $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['second']),
        );
    }

    public function testTheSaleOperationsAreNeverAskedAboutWhenTheCacheIsOff(): void
    {
        $saleAudienceChecker = $this->createMock(SaleAudienceChecker::class);
        $saleAudienceChecker->expects(self::never())->method('hasActiveReservedSale');

        $cache = new ResourceCache(
            new ArrayAdapter(),
            enabled: false,
            ttl: 60,
            allowedPrefixes: ['/api/front/products'],
            reservedSaleSensitivePrefixes: ['/api/front/products'],
            saleAudienceChecker: $saleAudienceChecker,
        );

        self::assertSame(['computed'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['computed']));
    }

    private function cache(bool $hasActiveReservedSale): ResourceCache
    {
        $saleAudienceChecker = $this->createMock(SaleAudienceChecker::class);
        $saleAudienceChecker->method('hasActiveReservedSale')->willReturn($hasActiveReservedSale);

        return new ResourceCache(
            new ArrayAdapter(),
            enabled: true,
            ttl: 60,
            allowedPrefixes: ['/api/front/products', '/api/front/product_sale_elements', '/api/front/countries'],
            reservedSaleSensitivePrefixes: ['/api/front/products', '/api/front/product_sale_elements'],
            saleAudienceChecker: $saleAudienceChecker,
        );
    }
}
