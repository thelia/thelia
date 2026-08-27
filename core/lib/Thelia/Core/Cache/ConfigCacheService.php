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

    public function __construct(protected AdapterInterface $cache)
    {
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
