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


namespace Thelia\Tests\Cache;

use Thelia\Cache\CacheFactory;
use Thelia\Cache\Driver\BaseCacheDriver;
use Thelia\Model\ConfigQuery;

class TCacheTest extends \PHPUnit_Framework_TestCase
{
    const NS_NULL_DRIVER = '\Thelia\Cache\Driver\NullDriver';
    const NS_FILE_DRIVER = '\Thelia\Cache\Driver\FileDriver';


    public function testInit()
    {
        $cacheFactory = new CacheFactory();

        ConfigQuery::write(BaseCacheDriver::CONFIG_DRIVER, self::NS_NULL_DRIVER);

        $cache = $cacheFactory->get();

        $this->assertInstanceOf(self::NS_NULL_DRIVER, $cache);

        $cache->save("FILE_KEY_1", "FILE CONTENT KEY 1");
        $this->assertEquals(false, $cache->fetch("FILE_KEY_1"));
    }
}
