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

namespace Thelia\Api\Service\API;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cross-request cache for the data access layer.
 *
 * Sits behind the per-request {@see ResourceMemoizer}: on a memoizer miss the
 * normalized payload of a read-stable front resource is looked up here, so it
 * survives across requests. Disabled by default; only user-independent paths
 * (the configured allow list) are eligible, and the whole pool is flushed on
 * any catalog change (see ResourceCacheInvalidationListener).
 */
readonly class ResourceCache
{
    /**
     * @param string[] $allowedPrefixes
     */
    public function __construct(
        #[Autowire(service: 'thelia.cache.data_access')]
        private CacheItemPoolInterface $pool,
        #[Autowire(param: 'thelia.api.data_access.cache.enabled')]
        private bool $enabled,
        #[Autowire(param: 'thelia.api.data_access.cache.ttl')]
        private int $ttl,
        #[Autowire(param: 'thelia.api.data_access.cache.allowed_prefixes')]
        private array $allowedPrefixes,
    ) {
    }

    /**
     * @param callable():(object|array|null) $compute
     */
    public function remember(string $key, string $path, callable $compute): object|array|null
    {
        if (!$this->enabled || !$this->isCacheable($path)) {
            return $compute();
        }

        $item = $this->pool->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        $value = $compute();

        // Never persist empty results: a transient miss (access denied, not
        // found) must not become a sticky cached value.
        if ($value !== null) {
            $item->set($value)->expiresAfter($this->ttl);
            $this->pool->save($item);
        }

        return $value;
    }

    public function clear(): void
    {
        $this->pool->clear();
    }

    private function isCacheable(string $path): bool
    {
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
