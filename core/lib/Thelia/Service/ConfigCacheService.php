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

namespace Thelia\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Thelia\Model\ConfigQuery;

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
        }

        $value = $this->cache->get(self::CACHE_KEY, static function (ItemInterface $item): array {
            $configs = ConfigQuery::create()->find();
            $caches = [];

            foreach ($configs as $config) {
                $caches[$config->getName()] = $config->getValue();
            }

            return $caches;
        });

        ConfigQuery::initCache($value);
    }
}
