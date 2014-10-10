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

use Thelia\Cache\Driver\ArrayDriver;
use Thelia\Cache\Driver\BaseCacheDriver;
use Thelia\Cache\Driver\FileDriver;
use Thelia\Cache\Driver\MemcachedDriver;
use Thelia\Cache\Driver\NullDriver;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;


/**
 * Class CacheFactory
 * @package Thelia\Cache
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheFactory
{

    const CONFIG_CACHE_ENABLED = 'tcache_enabled';
    const CONFIG_CACHE_DRIVER  = 'tcache_driver';
    const DEFAULT_CACHE_DRIVER = 'Null';


    /**
     *
     */
    private function __construct()
    {
    }


    /**
     * Create a new CacheFactory instance, that could be configured without interfering with the "main" instance
     *
     * @return \Thelia\Cache\Driver\BaseCacheDriver a new CacheFactory instance.
     */
    static public function get($driver = null, array $params = null)
    {

        if (null === $driver) {
            $driver = ConfigQuery::read(self::CONFIG_CACHE_DRIVER, self::DEFAULT_CACHE_DRIVER);
        }

        $instance = null;

        switch ($driver){
            case 'array':
                $instance = new ArrayDriver();
                break;
            case 'file':
                $instance = new FileDriver();
                break;
            case 'memcached':
                $instance = new MemcachedDriver();
                break;
            default;
                $instance = new NullDriver();
        }

        Tlog::getInstance()->debug(sprintf(" GU Cache : loading Drivr %s ", $driver));

        /** @var \Thelia\Cache\Driver\BaseCacheDriver $instance */
        $instance->init($params);

        return $instance;
    }

} 