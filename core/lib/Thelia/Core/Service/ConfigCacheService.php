<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Thelia\Model\ConfigQuery;

class ConfigCacheService
{
    protected $cache;

    public function __construct(AdapterInterface $cache)
    {
        $this->cache = $cache;
    }

    public function initCacheConfigs(bool $force = false): void
    {
        if ($force) {
            $this->cache->delete('thelia_config');
        }

        $value = $this->cache->get('thelia_config', function (ItemInterface $item) {
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
