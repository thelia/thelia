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

/**
 * This class provides URL Tool class initialisation
 *
 * @package Thelia\Tests\TestCaseWithURLSetup
 */
class TestCaseWithURLToolSetup extends \PHPUnit_Framework_TestCase
{
    public function __construct()
    {
        $this->setupURLTool();
    }

    protected function setupURLTool()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $context = new \Symfony\Component\Routing\RequestContext(
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

        $container->set("router.admin", $router);

        new \Thelia\Tools\URL($container);
    }
}
