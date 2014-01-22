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

namespace Thelia\Tests\Core\Template\Element;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Tools\URL;
use Thelia\TaxEngine\TaxEngine;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseLoopTestor extends \PHPUnit_Framework_TestCase
{
    protected $container;

    protected $instance;

    abstract public function getTestedClassName();
    abstract public function getTestedInstance();
    abstract public function getMandatoryArguments();

    protected function getMethod($name)
    {
        $class = new \ReflectionClass($this->getTestedClassName());
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function setUp()
    {
        $this->container = new ContainerBuilder();

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();

        $request->setSession($session);

        /*$stubEventdispatcher = $this->getMockBuilder('\Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();

        $stubSecurityContext = $this->getMockBuilder('\Thelia\Core\Security\SecurityContext')
            ->disableOriginalConstructor()
            ->getMock();*/

        /*$stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));*/

        /*$this->request = new Request();
        $this->request->setSession(new Session(new MockArraySessionStorage()));

        $this->dispatcher = new EventDispatcher();

        $this->securityContext = new SecurityContext($this->request);*/

        $stubRouterAdmin = $this->getMockBuilder('\Symfony\Component\Routing\Router')
            ->disableOriginalConstructor()
            ->setMethods(array('getContext'))
            ->getMock();

        $stubRequestContext = $this->getMockBuilder('\Symfony\Component\Routing\RequestContext')
            ->disableOriginalConstructor()
            ->setMethods(array('getHost'))
            ->getMock();

        $stubRequestContext->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('localhost'));

        $stubRouterAdmin->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue(
                $stubRequestContext
            ));

        $this->container->set('request', $request);
        $this->container->set('event_dispatcher', new EventDispatcher());
        $this->container->set('thelia.securityContext', new SecurityContext($request));
        $this->container->set('router.admin', $stubRouterAdmin);
        $this->container->set('thelia.url.manager', new URL($this->container));
        $this->container->set('thelia.taxEngine', new TaxEngine($request));

        $this->instance = $this->getTestedInstance();
        $this->instance->initializeArgs($this->getMandatoryArguments());
    }

    public function testGetArgDefinitions()
    {
        $method = $this->getMethod('getArgDefinitions');

        $methodReturn = $method->invoke($this->instance);

        $this->assertInstanceOf('Thelia\Core\Template\Loop\Argument\ArgumentCollection', $methodReturn);
    }

    public function testExec()
    {
        $method = $this->getMethod('exec');

        $methodReturn = $method->invokeArgs($this->instance, array(null));

        $this->assertInstanceOf('\Thelia\Core\Template\Element\LoopResult', $methodReturn);
    }

    public function baseTestSearchById($id, $other_args = array())
    {
        $this->instance->initializeArgs(array_merge(
            $this->getMandatoryArguments(),
            array(
                "type" => "foo",
                "name" => "foo",
                "id" => $id,
            ),
            $other_args
        ));

        $dummy = null;
        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals(1, $loopResults->getCount());

        $substitutions = $loopResults->current()->getVarVal();

        $this->assertEquals($id, $substitutions['ID']);
    }

    public function baseTestSearchWithLimit($limit)
    {
        $this->instance->initializeArgs(array_merge(
            $this->getMandatoryArguments(),
            array(
                "type" => "foo",
                "name" => "foo",
                "limit" => $limit,
            )
        ));

        $dummy = null;
        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals($limit, $loopResults->getCount());
    }
}
