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
use Thelia\Model\ConfigQuery;

class TCacheTest extends \PHPUnit_Framework_TestCase {

    const NS_NULL_DRIVER = '\Thelia\Cache\Driver\NullDriver';
    const NS_FILE_DRIVER = '\Thelia\Cache\Driver\FileDriver';
    const NS_APC_DRIVER = '\Thelia\Cache\Driver\ApcDriver';



    public function testInit()
    {

        ConfigQuery::write(CacheFactory::CONFIG_CACHE_DRIVER, self::NS_FILE_DRIVER);

        $cache = CacheFactory::getInstance();

        assertInstanceOf(self::NS_FILE_DRIVER, $cache);

        $cache->save("FILE_KEY_1", "FILE CONTENT KEY 1");
        $this->assertEquals("FILE CONTENT KEY 1", $cache->fetch("FILE_KEY_1"));

    }


}


