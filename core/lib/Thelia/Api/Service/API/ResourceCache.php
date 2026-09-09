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
use Thelia\Domain\Sale\SaleAudienceChecker;

/**
 * Cross-request cache for the data access layer.
 *
 * Sits behind the per-request {@see ResourceMemoizer}: on a memoizer miss the
 * normalized payload of a read-stable front resource is looked up here, so it
 * survives across requests. Disabled by default; only user-independent paths
 * (the configured allow list) are eligible, and the whole pool is flushed on
 * any catalog change (see ResourceCacheInvalidationListener).
 *
 * The key holds the path, the format and the locale, and deliberately not who
 * is asking — which is only sound while the answer is the same for everybody.
 * A running reserved operation breaks that for the catalog: two visitors asking
 * for the same product are owed two different prices. Those paths are therefore
 * bypassed for as long as such an operation runs, and cached again the moment
 * none does, so a shop that never runs one keeps every bit of its cache.
 */
readonly class ResourceCache
{
    /**
     * @param string[] $allowedPrefixes
     * @param string[] $reservedSaleSensitivePrefixes
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
        #[Autowire(param: 'thelia.api.data_access.cache.reserved_sale_sensitive_prefixes')]
        private array $reservedSaleSensitivePrefixes,
        private SaleAudienceChecker $saleAudienceChecker,
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
                return !$this->answerDependsOnWhoIsAsking($path);
            }
        }

        return false;
    }

    /**
     * Whether the answer to this path stopped being the same for everybody.
     *
     * The operations are only asked about for a path a reserved price can travel
     * on, and the answer is memoised for the request: a shop with no reserved
     * operation pays one indexed existence check per request for its whole cache.
     */
    private function answerDependsOnWhoIsAsking(string $path): bool
    {
        foreach ($this->reservedSaleSensitivePrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->saleAudienceChecker->hasActiveReservedSale();
            }
        }

        return false;
    }
}
