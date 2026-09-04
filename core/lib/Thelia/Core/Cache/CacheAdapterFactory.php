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

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Builds the backend every application cache pool is stored in.
 *
 * The choice belongs to the hosting, not to the shop: THELIA_CACHE_DSN empty
 * keeps the cache on the local file system, so an installation that configures
 * nothing depends on nothing. Filled, every pool moves to that server, which is
 * what a shop spread over several front ends needs.
 *
 * A data source name that cannot be honoured stops the request instead of
 * falling back to the disk: a shop that believes its cache is shared while each
 * front end keeps its own would serve inconsistent pages and sign API clients
 * out at random.
 *
 * Every backend is returned wrapped in {@see ApplicationCacheAdapter}, so the
 * pools have one class whatever the data source name says. Symfony reads that
 * class when it compiles the container, well before the variable is looked at.
 */
final class CacheAdapterFactory
{
    /**
     * Named in the error messages so an operator knows what to fix.
     */
    public const DSN_VARIABLE = 'THELIA_CACHE_DSN';

    private const REMOTE_SCHEMES = ['redis', 'rediss', 'valkey', 'valkeys', 'memcached'];

    /**
     * The first two arguments are replaced by Symfony for each pool built on
     * this adapter, so their order is part of the contract with CachePoolPass.
     */
    public static function create(
        string $namespace,
        int $defaultLifetime,
        #[\SensitiveParameter] string $dsn,
        string $directory,
        ?MarshallerInterface $marshaller = null,
    ): ApplicationCacheAdapter {
        $dsn = trim($dsn);

        if ('' === $dsn) {
            return new ApplicationCacheAdapter(new FilesystemAdapter($namespace, $defaultLifetime, $directory, $marshaller));
        }

        try {
            return new ApplicationCacheAdapter(self::remote($dsn, $namespace, $defaultLifetime, $marshaller));
        } catch (\Throwable $failure) {
            throw new \InvalidArgumentException(\sprintf('%s is set to "%s", which cannot be used as a cache backend: %s', self::DSN_VARIABLE, self::withoutPassword($dsn), $failure->getMessage()), 0, $failure);
        }
    }

    private static function remote(
        #[\SensitiveParameter] string $dsn,
        string $namespace,
        int $defaultLifetime,
        ?MarshallerInterface $marshaller,
    ): AdapterInterface&CacheInterface {
        $scheme = strstr($dsn, ':', true);

        if (!\in_array($scheme, self::REMOTE_SCHEMES, true)) {
            throw new \InvalidArgumentException(\sprintf('the scheme is not one of %s://', implode('://, ', self::REMOTE_SCHEMES)));
        }

        $connection = AbstractAdapter::createConnection($dsn);

        if ($connection instanceof \Memcached) {
            return new MemcachedAdapter($connection, $namespace, $defaultLifetime, $marshaller);
        }

        return new RedisAdapter($connection, $namespace, $defaultLifetime, $marshaller);
    }

    private static function withoutPassword(#[\SensitiveParameter] string $dsn): string
    {
        return preg_replace('#^([a-z][a-z0-9+.-]*://[^:@/]*):[^@]*@#i', '$1:***@', $dsn) ?? $dsn;
    }
}
