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

namespace TheliaSmarty\Template\Assets;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

class EncoreModuleAssetsVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        private $originPath,
        private bool $debug,
        private AdapterInterface $cache
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->debug ?
            md5_file($this->originPath.DS.$path) :
            $this->cache->get('thelia_module_assets_'.urlencode($path), function (ItemInterface $item) use ($path) {
                return md5_file($this->originPath.DS.$path);
            });
    }

    public function applyVersion(string $path): string
    {
        return sprintf('%s?v=%s', $path, $this->getVersion($path));
    }
}
