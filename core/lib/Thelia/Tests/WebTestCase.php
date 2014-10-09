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

namespace Thelia\Tests;

use Thelia\Core\Thelia;

/**
 * Class WebTestCase
 * @package Thelia\Tests
 * @author Manuel Raynaud <manu@thelia.net>
 */
class WebTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Thelia\Core\Thelia
     */
    protected static $kernel;

    /**
     * @param  array                                $options
     * @param  array                                $server
     * @return \Symfony\Component\HttpKernel\Client
     */
    protected static function createClient(array $options = [], array $server = [])
    {
        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }

        static::$kernel = new Thelia('test', true);
        static::$kernel->boot();

        $client = static::$kernel->getContainer()->get('test.client');
        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    protected function tearDown()
    {
        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }
    }
}
