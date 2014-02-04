<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Tests;

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
}
