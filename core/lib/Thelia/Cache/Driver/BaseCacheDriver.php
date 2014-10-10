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


namespace Thelia\Cache\Driver;

use Doctrine\Common\Cache\CacheProvider;
use Thelia\Model\ConfigQuery;


/**
 * Class CacheDriverInterface
 * @package Thelia\Cache\Driver
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
abstract class BaseCacheDriver implements CacheDriverInterface
{

    const CONFIG_LIFE_TIME = 'tcache_life_time';

    const DEFAULT_LIFE_TIME = 30;


    /** @var CacheProvider The doctrine cache instance */
    protected $cache = null;

    /** @var bool Is the cache feature is activated or not */
    protected $sleep = false;

    /** @var bool Default life time for entry */
    protected $lifeTime = null;


    /**
     * @inheritdoc
     */
    public abstract function init(array $params = null);


    protected function initDefault(array $params = null)
    {
        $this->lifeTime = $this->getParam(
            $params,
            "lifetime",
            self::CONFIG_LIFE_TIME,
            self::DEFAULT_LIFE_TIME
        );
    }

    /**
     * @inheritdoc
     */
    public function contains($id)
    {
        return $this->cache->contains($id);
    }

    /**
     * @inheritdoc
     */
    public function save($id, $data, $refs = [], $lifeTime = null)
    {
        if ($this->sleep) {
            return false;
        }

        if (null === $lifeTime) {
            $lifeTime = $this->lifeTime;
        }

        $this->cache->save($id, $data, $lifeTime);

        if (is_array($refs)) {
            foreach ($refs as $ref) {
                $this->addRef($ref, $id);
            }
        } elseif (is_string($refs)) {
            $this->addRef($refs, $id);
        }
    }

    /**
     * @inheritdoc
     */
    public function fetch($id)
    {
        return !$this->sleep ? $this->cache->fetch($id) : null;
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        $this->cache->delete($id);
    }

    /**
     * @inheritdoc
     */
    public function deleteRef($ref)
    {
        $deleted = 0;
        $keys    = $this->cache->fetch($ref);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if ($this->cache->delete($key)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        $this->cache->deleteAll();
    }

    /**
     * @inheritdoc
     */
    public function getStats()
    {
        $this->cache->getStats();
    }

    /**
     * @inheritdoc
     */
    public function sleep()
    {
        $this->sleep = true;
    }

    /**
     * @inheritdoc
     */
    public function wakeUp()
    {
        $this->sleep = false;
    }

    /**
     * @inheritdoc
     */
    public function isSleeping()
    {
        return $this->sleep;
    }

    /**
     * Add a reference to the cache with key $key
     *
     * @param string $ref the reference key
     * @param string $key the cache key
     *
     * @return boolean true if the ref hs been added, false otherwise
     */
    protected function addRef($ref, $key)
    {
        $content = $this->cache->fetch($ref);
        if (!is_array($content)) {
            $content = [];
        }
        $content[] = $key;

        return $this->cache->save($ref, $content, 0);
    }

    /**
     * Extract a param from the params array
     *
     * @param array $params array of parameters
     * @param string $key the param key
     * @param string $configKey the config key to search if key is not found in params
     * @param mixed $default a default value
     *
     * @return mixed|null
     */
    protected function getParam($params, $key, $configKey, $default)
    {
        $ret = null;

        if (is_array($params) && array_key_exists($key, $params) && "" !== $params[$key]) {
            $ret = $params[$key];
        } else {
            $ret = ConfigQuery::read($configKey, $default);
            if ("" === $ret) {
                $ret = $default;
            }
        }

        return $ret;
    }


}