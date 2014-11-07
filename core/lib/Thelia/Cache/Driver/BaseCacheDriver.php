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

    const CONFIG_ENABLED = 'tcache_enabled';

    const CONFIG_DRIVER = 'tcache_driver';

    const DEFAULT_DRIVER = 'null';

    const CONFIG_NAMESPACE = 'tcache_namespace';

    const DEFAULT_NAMESPACE = '';

    const CONFIG_LIFE_TIME = 'tcache_life_time';

    const DEFAULT_LIFE_TIME = 30;


    /** @var CacheProvider The doctrine cache instance */
    protected $cache = null;

    /** @var bool Is the cache feature is activated or not */
    protected $sleep = false;

    /** @var int Default life time for entry */
    protected $lifeTime = null;

    /** @var string Default namespace for entry */
    protected $namespace = null;

    /**
     * @inheritdoc
     */
    public function init(array $params = null)
    {
        $this->initDefault($params);

        $this->initDriver($params);

        $this->postInit($params);
    }

    protected function initDefault(array $params = null)
    {
        $this->lifeTime = $this->getParam(
            $params,
            "lifetime",
            self::CONFIG_LIFE_TIME,
            self::DEFAULT_LIFE_TIME
        );

        $this->namespace = $this->getParam(
            $params,
            "namespace",
            self::CONFIG_NAMESPACE,
            self::DEFAULT_NAMESPACE
        );
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

    protected abstract function initDriver();

    protected function postInit($params)
    {

        if (null !== $this->cache) {
            $this->cache->setNamespace($this->namespace);
        }
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
        return $this->cache->delete($id);
    }

    /**
     * @inheritdoc
     */
    public function deleteRef($ref)
    {
        $deleted = 0;
        $keys = $this->cache->fetch($ref);
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
    public function flush()
    {
        return $this->cache->flushAll();
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        return $this->cache->deleteAll();
    }

    /**
     * @inheritdoc
     */
    public function getStats()
    {
        return $this->cache->getStats();
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
}