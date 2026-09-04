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

namespace Thelia\Core\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * The application cache pools, whatever backend they turned out to use.
 *
 * Which backend that is depends on THELIA_CACHE_DSN and is only known once the
 * pool is built, while Symfony decides at compile time, from the class of the
 * adapter, whether a pool can be pruned. A single class answering for every
 * backend is what keeps `cache:pool:prune` reaching the pools: without it the
 * command silently prunes nothing and expired entries no one reads again stay
 * on the disk until the pool is emptied whole.
 *
 * Pruning is the only capability that actually varies, and a cache server
 * expires its own keys: there is nothing left to prune and nothing failed, so
 * `prune()` reports success. Reporting failure would make `cache:pool:prune`
 * answer "could not be pruned" on every pool of a shop running on Redis, and
 * exit non zero in the middle of a deployment script.
 */
final class ApplicationCacheAdapter implements AdapterInterface, CacheInterface, LoggerAwareInterface, NamespacedPoolInterface, PruneableInterface, ResettableInterface
{
    public function __construct(
        private AdapterInterface&CacheInterface $inner,
    ) {
    }

    /**
     * The backend in use, for the tests and for diagnostics.
     */
    public function inner(): AdapterInterface&CacheInterface
    {
        return $this->inner;
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->inner->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->inner->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->inner->hasItem($key);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->inner->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->inner->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->inner->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->inner->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->inner->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->inner->commit();
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->inner->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function prune(): bool
    {
        return !$this->inner instanceof PruneableInterface || $this->inner->prune();
    }

    public function reset(): void
    {
        if ($this->inner instanceof ResettableInterface) {
            $this->inner->reset();
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->inner instanceof LoggerAwareInterface) {
            $this->inner->setLogger($logger);
        }
    }

    public function withSubNamespace(string $namespace): static
    {
        if (!$this->inner instanceof NamespacedPoolInterface) {
            throw new \BadMethodCallException(\sprintf('Cannot call "%s::withSubNamespace()": the backend "%s" does not implement "%s".', self::class, get_debug_type($this->inner), NamespacedPoolInterface::class));
        }

        $clone = clone $this;
        $clone->inner = $this->inner->withSubNamespace($namespace);

        return $clone;
    }
}
