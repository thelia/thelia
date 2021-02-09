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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Cache;
use Thelia\Core\Event\Cache\CacheEvent;

/**
 * Class CacheTest
 * @package Thelia\Tests\Action\assets
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class CacheTest extends TestCase
{
    protected $dir;
    protected $dir2;

    public function setUp():void
    {
        $this->dir = __DIR__ . '/test';
        $this->dir2 = __DIR__ . '/test2';

        $fs = new Filesystem();
        $fs->mkdir($this->dir);
        $fs->mkdir($this->dir2);
    }

    public function testCacheClear()
    {
        $event = new CacheEvent($this->dir, false);

        $adapter = new ArrayAdapter();
        $action = new Cache($adapter);
        $action->cacheClear($event);

        $fs = new Filesystem();
        $this->assertFalse($fs->exists($this->dir));
    }

    public function testKernelTerminateCacheClear()
    {
        $event = new CacheEvent($this->dir2);

        $adapter = new ArrayAdapter();
        $action = new Cache($adapter);
        $action->cacheClear($event);

        $fs = new Filesystem();
        $this->assertTrue($fs->exists($this->dir2));

        $action->onTerminate();

        $this->assertFalse($fs->exists($this->dir2));
    }
}
