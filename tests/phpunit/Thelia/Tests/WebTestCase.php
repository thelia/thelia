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
use Thelia\Model\ConfigQuery;

/**
 * Class WebTestCase
 * @package Thelia\Tests
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class WebTestCase extends \PHPUnit_Framework_TestCase
{
    protected $isMultiDomainActivated;

    protected function setUp()
    {
        // We have to shut down the "One domain for each lang feature" before tests,
        // to prevent 302 redirections during the tests.
        $this->isMultiDomainActivated = ConfigQuery::read('one_domain_foreach_lang');

        ConfigQuery::write('one_domain_foreach_lang', false);
    }

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
        ConfigQuery::write('one_domain_foreach_lang', $this->isMultiDomainActivated);

        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }
    }
}
