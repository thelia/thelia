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

namespace Thelia\Core\Security\RefreshToken;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cache-backed JWT refresh token storage with single-use rotation.
 *
 * The token itself is opaque (cryptographically random). The cache item
 * key is the token, the value is a tuple { username, scope }. Lookup
 * deletes the item before returning, so the same refresh token cannot
 * be replayed: callers MUST issue a fresh one in the same flow.
 *
 * Storage is the `thelia.cache.security` pool, which nothing empties on a
 * deployment or on a cache clear, so releasing a new version does not sign the
 * API clients out. Eviction still forces a re-login: a cache server configured
 * to drop the oldest keys under memory pressure will disconnect clients.
 */
final readonly class RefreshTokenService
{
    public const SCOPE_ADMIN = 'admin';
    public const SCOPE_CUSTOMER = 'customer';

    private const CACHE_NAMESPACE = 'jwt_refresh_';
    private const TOKEN_BYTES = 64;

    public function __construct(
        #[Autowire(service: 'thelia.cache.security')]
        private CacheItemPoolInterface $cache,
        #[Autowire(param: 'thelia.security.jwt_refresh_token_ttl')]
        private int $ttl,
    ) {
    }

    public function issue(string $username, string $scope): string
    {
        $this->assertScope($scope);

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $item = $this->cache->getItem(self::CACHE_NAMESPACE.$token);
        $item->set(['username' => $username, 'scope' => $scope]);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $token;
    }

    /**
     * @return array{username: string, scope: string}|null
     */
    public function consume(string $token): ?array
    {
        $key = self::CACHE_NAMESPACE.$token;
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        $payload = $item->get();
        $this->cache->deleteItem($key);

        if (!\is_array($payload) || !isset($payload['username'], $payload['scope'])) {
            return null;
        }

        return $payload;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    private function assertScope(string $scope): void
    {
        if (!\in_array($scope, [self::SCOPE_ADMIN, self::SCOPE_CUSTOMER], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown refresh token scope "%s".', $scope));
        }
    }
}
