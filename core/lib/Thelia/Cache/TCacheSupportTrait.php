<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace Thelia\Cache;


use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Cache\Driver\CacheDriverInterface;


trait TCacheSupportTrait
{

    /** @var \Thelia\Cache\Driver\BaseCacheDriver $cache */
    protected $cache = null;

    public function setCacheManager(CacheDriverInterface $cache)
    {
        $this->cache = $cache;
    }

    protected function getCacheManager()
    {
        if (null === $this->cache) {
            // try to load the default cache manager from the container
            if (parent instanceof ContainerAwareInterface) {
                /** @var ContainerInterface $container */
                $container = $this->getContainer();
                if (null !== $container) {
                    $this->cache = $container->get('thelia.cache');
                }
            }
        }

        return $this->cache;
    }

    protected function hasCacheManager()
    {
        return (null !== $this->cache);
    }

    protected function hasCache($key)
    {
        $cache   = $this->getCacheManager();

        if (null !== $cache && null !== $key) {
            return $this->cache->contains($key);
        }

        return false;
    }

    protected function getCache($key)
    {
        $cache   = $this->getCacheManager();
        $content = null;
        if (null !== $cache && null !== $key) {
            $content = $cache->fetch($key);
            if (false === $content) {
                $content = null;
            }
        }

        return $content;
    }

    protected function setCache($key, $content, $refs = [], $ttl = null)
    {
        $cached = false;

        if (null !== $cache = $this->getCacheManager()) {
            $cached = $cache->save($key, $content, $refs, $ttl);
        }

        return $cached;
    }
}