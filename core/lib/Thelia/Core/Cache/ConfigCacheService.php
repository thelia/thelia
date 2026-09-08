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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Thelia\Model\ConfigQuery;

/**
 * Keeps the configuration table in a cache shared by every process of the shop.
 *
 * The entry holds stored values only. An environment variable overriding a
 * configuration name belongs to the process that declares it, and is applied by
 * {@see ConfigQuery::read()} on each read, so a process started with an override
 * can neither publish it to the others nor read theirs.
 */
class ConfigCacheService
{
    public const CACHE_KEY = 'thelia_config';

    /**
     * Namespace of the pool holding the entry.
     *
     * Given to the container's pool definition as a literal, not read back
     * from it: {@see warmFromSharedEntry()} has to reach the same files
     * before there is a container to ask, and only a value known in advance
     * lets it. It only ever finds them when THELIA_CACHE_DSN is empty, the
     * pool then being the plain local FilesystemAdapter this builds by hand;
     * a remote backend leaves the pre-boot read a miss, same as an empty one.
     */
    public const CACHE_NAMESPACE = 'thelia_cache';

    public function __construct(protected AdapterInterface $cache)
    {
    }

    /**
     * Hands the stored configuration over before there is a container.
     *
     * The debug logger reads its own configuration while Propel is being
     * wired up, long before this service exists, and with nothing warmed
     * {@see ConfigQuery::read()} read the whole table straight from the
     * database - on every single request. This reads the shared entry and
     * nothing else: filling it stays the business of the service, so a miss
     * simply leaves the configuration unwarmed, exactly as before.
     */
    public static function warmFromSharedEntry(string $cacheDirectory): void
    {
        $item = (new FilesystemAdapter(self::CACHE_NAMESPACE, 0, $cacheDirectory))
            ->getItem(self::CACHE_KEY);

        if (!$item->isHit()) {
            return;
        }

        $configs = $item->get();

        if (\is_array($configs)) {
            ConfigQuery::initCache($configs);
        }
    }

    public function initCacheConfigs(bool $force = false): void
    {
        if ($force) {
            $this->cache->delete(self::CACHE_KEY);

            // Reload for the remainder of the request only: the shared entry is
            // rebuilt by the next request, so an uncommitted value is never
            // published to it. Without this, every ConfigQuery::read() left in
            // the request would fall back to the default it was given.
            ConfigQuery::initCache(self::loadConfigs());

            return;
        }

        $value = $this->cache->get(self::CACHE_KEY, static fn (ItemInterface $item): array => self::loadConfigs());

        ConfigQuery::initCache($value);
    }

    /**
     * @return array<string, string|null>
     */
    private static function loadConfigs(): array
    {
        return ConfigQuery::findAllAsMap();
    }
}
