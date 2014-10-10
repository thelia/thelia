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


trait TCacheSupportTrait
{

    /** @var \Thelia\Cache\Driver\BaseCacheDriver $cache */
    protected $cache = null;

    protected function getCacheManager()
    {
        if (null === $this->cache) {
            $this->cache = CacheFactory::getInstance();
            //$this->cache->sleep();
        }

        return $this->cache;
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

    protected function setCache($key, $content, $refs = array())
    {

        $cached = false;
        if (null !== $cache = $this->getCacheManager()) {
            $cached = $cache->save($key, $content, $refs);
        }

        return $cached;
    }

} 