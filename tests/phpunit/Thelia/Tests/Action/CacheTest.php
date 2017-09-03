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

namespace Thelia\Tests\Action;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Cache;
use Thelia\Core\Event\Cache\CacheEvent;

/**
 * Class CacheTest
 * @package Thelia\Tests\Action\assets
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CacheTest extends \PHPUnit_Framework_TestCase
{
    protected $dir;

    public function setUp()
    {
        $this->dir = __DIR__ . '/test';

        $fs = new Filesystem();
        $fs->mkdir($this->dir);
    }

    public function testCacheClear()
    {
        $event = new CacheEvent($this->dir);

        $adapter = new ArrayAdapter();
        $action = new Cache($adapter);
        $action->cacheClear($event);

        $fs = new Filesystem();
        $this->assertFalse($fs->exists($this->dir));
    }
}
