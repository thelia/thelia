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

namespace Thelia\Tests\Command;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Cache;
use Thelia\Core\Application;
use Thelia\Command\CacheClear;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * test the cache:clear command
 *
 * Class CacheClearTest
 * @package Thelia\Tests\Command
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CacheClearTest extends ContainerAwareTestCase
{
    public $cache_dir;

    public function setUp()
    {
        $this->cache_dir = THELIA_CACHE_DIR . 'test_cache';

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
            $fs->chmod($this->cache_dir, 0100);

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

    /**
     * Use this method to build the container with the services that you need.
     * @param ContainerBuilder $container
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new Cache(new ArrayAdapter()));

        $container->set("event_dispatcher", $eventDispatcher);

        $container->setParameter("kernel.cache_dir", $this->cache_dir);
    }
}
