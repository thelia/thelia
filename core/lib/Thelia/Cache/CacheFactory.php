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
use Thelia\Model\ConfigQuery;

/**
 * Class CacheFactory
 * @package Thelia\Cache
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CacheFactory
{
    /**
     *  Create a new CacheFactory instance, that could be configured without interfering with the "main" instance
     *
     * @param string|null $driver The driver name
     * @param array $params
     * @param bool $fallback
     * @return null|ArrayDriver|BaseCacheDriver|FileDriver|MemcachedDriver|NullDriver
     */
    public function get($driver = null, array $params = null, $fallback = true)
    {

        if (null === $driver) {
            $driver = ConfigQuery::read(
                BaseCacheDriver::CONFIG_DRIVER,
                BaseCacheDriver::DEFAULT_DRIVER
            );
        }

        $instance = null;

        switch ($driver) {
            case 'array':
                $instance = new ArrayDriver();
                break;
            case 'file':
                $instance = new FileDriver();
                break;
            case 'memcached':
                $instance = new MemcachedDriver();
                break;
            case 'null':
                $instance = new NullDriver();
                break;
            default:
                if (true === $fallback) {
                    $instance = new NullDriver();
                } else {
                    throw new \InvalidArgumentException("No drivers match !");
                }
        }

        try {
            /** @var \Thelia\Cache\Driver\BaseCacheDriver $instance */
            $instance->init($params);
        } catch (\RuntimeException $ex) {
            if ($fallback) {
                $instance = new NullDriver();
            }
        }

        return $instance;
    }
}
