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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Thelia\Tools\URL;

/**
 * This class provides URL Tool class initialisation
 *
 * @package Thelia\Tests\TestCaseWithURLSetup
 */
class TestCaseWithURLToolSetup extends \PHPUnit_Framework_TestCase
{
    private $container = null;
    private $dispatcher = null;

    public function __construct()
    {
        $this->container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $this->container->set("event_dispatcher", $this->dispatcher);

        $this->setupURLTool();
    }

    protected function setupURLTool()
    {
        $context = new RequestContext(
            '/thelia/index.php',
            'GET',
            'localhost',
            'http',
            80,
            443,
            '/path/to/action'
        );

        $router = $this->getMockBuilder("Symfony\Component\Routing\Router")
        ->disableOriginalConstructor()
        ->getMock();

        $router->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $this->container->set("router.admin", $router);

        new URL($this->container);
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function getMockEventDispatcher()
    {
        return $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }
}
