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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\RefreshToken\RefreshTokenService;
use Thelia\Test\IntegrationTestCase;

/**
 * The application cache is split per use, and each part is emptied on its own.
 * The one holding API refresh tokens is the only one whose eviction is visible
 * to a user: emptying the shop cache, from the back office or from the command
 * line, must not log the API clients out.
 */
final class ApplicationCachePoolsTest extends IntegrationTestCase
{
    private const PROBE_KEY = 'application-cache-pools-probe';

    protected function tearDown(): void
    {
        foreach (['thelia.cache.security', 'thelia.cache.data_access'] as $poolId) {
            $this->pool($poolId)->deleteItem(self::PROBE_KEY);
        }

        parent::tearDown();
    }

    public function testTheSecurityPoolIsNotTheDataAccessPool(): void
    {
        $security = $this->pool('thelia.cache.security');
        $dataAccess = $this->pool('thelia.cache.data_access');

        $this->write($security, 'kept');
        $this->write($dataAccess, 'dropped');

        $dataAccess->clear();

        self::assertTrue(
            $security->getItem(self::PROBE_KEY)->isHit(),
            'Emptying the data access pool must leave the security pool alone.',
        );
        self::assertFalse($dataAccess->getItem(self::PROBE_KEY)->isHit());
    }

    public function testTheSecurityPoolSurvivesTheApplicationPoolBeingEmptied(): void
    {
        $security = $this->pool('thelia.cache.security');
        $this->write($security, 'kept');

        $this->pool('cache.app')->clear();

        self::assertTrue($security->getItem(self::PROBE_KEY)->isHit());
    }

    public function testClearingTheShopCacheKeepsTheApiClientsSignedIn(): void
    {
        $refreshTokens = $this->getService(RefreshTokenService::class);
        $token = $refreshTokens->issue('cache-pools@example.org', RefreshTokenService::SCOPE_CUSTOMER);

        $throwaway = sys_get_temp_dir().'/thelia-cache-clear-probe';

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(
            new CacheEvent($throwaway, onKernelTerminate: false, invalidatePropelSchema: false),
            TheliaEvents::CACHE_CLEAR,
        );

        self::assertNotNull(
            $refreshTokens->consume($token),
            'A refresh token must outlive a cache clear, otherwise every deployment signs the API clients out.',
        );
    }

    private function pool(string $id): CacheItemPoolInterface
    {
        $pool = static::getContainer()->get($id);
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);

        return $pool;
    }

    private function write(CacheItemPoolInterface $pool, string $value): void
    {
        $item = $pool->getItem(self::PROBE_KEY);
        $item->set($value);
        $pool->save($item);
    }
}
