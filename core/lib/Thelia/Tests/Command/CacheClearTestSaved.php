<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Core\Application;
use Thelia\Command\CacheClear;

use Symfony\Component\Filesystem\Filesystem;

/**
 * test the cache:clear command
 *
 * Class CacheClearTest
 * @package Thelia\Tests\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CacheClearTestSaved extends \PHPUnit_Framework_TestCase
{

    public $cache_dir;

    public function setUp()
    {
        $this->cache_dir = THELIA_ROOT . "cache/test";

        $fs = new Filesystem();

        $fs->mkdir($this->cache_dir);
        $fs->mkdir(THELIA_WEB_DIR . "/assets");
    }

    public function testCacheClear()
    {
        // Fails on windows - do not execute this test on windows
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $application = new Application($this->getKernel());

            $cacheClear = new CacheClear();
            $cacheClear->setContainer($this->getContainer());

            $application->add($cacheClear);

            $command = $application->find("cache:clear");
            $commandTester = new CommandTester($command);
            $commandTester->execute(array(
                "command" => $command->getName(),
                "--env" => "test"
            ));

            $fs = new Filesystem();

            $this->assertFalse($fs->exists($this->cache_dir));
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCacheClearWithoutWritePermission()
    {
        // Fails on windows - mock this test on windows
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $fs = new Filesystem();
            $fs->chmod($this->cache_dir,0100);

            $application = new Application($this->getKernel());

            $cacheClear = new CacheClear();
            $cacheClear->setContainer($this->getContainer());

            $application->add($cacheClear);

            $command = $application->find("cache:clear");
            $commandTester = new CommandTester($command);
            $commandTester->execute(array(
                "command" => $command->getName(),
                "--env" => "test"
            ));
        } else {
            throw new \RuntimeException("");
        }
    }

    public function getKernel()
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $container->setParameter("kernel.cache_dir", $this->cache_dir);

        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $container->set("event_dispatcher", $dispatcher);

        return $container;
    }

}
