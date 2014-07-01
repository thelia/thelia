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

use Thelia\Cache\Driver\BaseCacheDriver;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;


/**
 * Class TCache
 * @package Thelia\Cache
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TCache {

    /** @var BaseCacheDriver $instance  */
    protected static $instance = null;

    const CONFIG_CACHE_DRIVER = 'tcache_driver';

    const DEFAULT_CACHE_DRIVER = '\Thelia\Cache\Driver\NullDriver';


    /**
     *
     */
    private function __construct() {}

    /**
     *
     * @return \Thelia\Cache\Driver\BaseCacheDriver
     */
    public static function getInstance()
    {
        if (self::$instance == false) {
            self::$instance = self::getNewInstance();
        }

        return self::$instance;
    }

    /**
     * Create a new TCache instance, that could be configured without interfering with the "main" instance
     *
     * @return \Thelia\Cache\Driver\BaseCacheDriver a new TCache instance.
     */
    public static function getNewInstance()
    {
        $driver = ConfigQuery::read(self::CONFIG_CACHE_DRIVER, self::DEFAULT_CACHE_DRIVER);
        Tlog::getInstance()->debug(sprintf(" GU Cache : loading Drivr %s ", $driver));

        /** @var \Thelia\Cache\Driver\BaseCacheDriver $instance */
        $r = new \ReflectionClass($driver);
        $instance = $r->newInstance();

        $instance->init();

        return $instance;
    }

} 