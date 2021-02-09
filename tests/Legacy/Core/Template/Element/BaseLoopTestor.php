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

namespace Thelia\Tests\Core\Template\Element;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseLoopTestor extends TestCase
{
    protected $container;

    protected $instance;

    abstract public function getMandatoryArguments();

    protected function getMethod($name)
    {
        $class = new \ReflectionClass($this->getTestedClassName());
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $this->container->setParameter(
            "Thelia.parser.loops",
            [
                "tested-loop" => $this->getTestedClassName()
            ]
        );

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $requestStack = new RequestStack();

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
            ->setMethods(['getContext'])
            ->getMock();

        $stubRequestContext = $this->getMockBuilder('\Symfony\Component\Routing\RequestContext')
            ->disableOriginalConstructor()
            ->setMethods(['getHost'])
            ->getMock();

        $stubRequestContext->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('localhost'));

        $stubRouterAdmin->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue(
                $stubRequestContext
            ));

        $requestStack->push($request);
        $this->container->set('request', $request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set('event_dispatcher', new EventDispatcher());
        $this->container->set('thelia.translator', new Translator($requestStack));
        $this->container->set('thelia.securityContext', new SecurityContext($requestStack));
        $this->container->set('router.admin', $stubRouterAdmin);
        $this->container->set('thelia.url.manager', new URL($stubRouterAdmin));
        $this->container->set('thelia.taxEngine', new TaxEngine($requestStack));

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
        $page = 0;
        $methodReturn = $method->invokeArgs($this->instance, [&$page]);

        $this->assertInstanceOf('\Thelia\Core\Template\Element\LoopResult', $methodReturn);
    }

    public function baseTestSearchById($id, $other_args = [])
    {
        $this->instance->initializeArgs(array_merge(
            $this->getMandatoryArguments(),
            [
                "type" => "foo",
                "name" => "foo",
                "id" => $id,
            ],
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
            [
                "type" => "foo",
                "name" => "foo",
                "limit" => $limit,
            ]
        ));

        $dummy = null;
        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals($limit, $loopResults->getCount());
    }

    protected function getTestedInstance()
    {
        $className = $this->getTestedClassName();

        return new $className(
            $this->container,
            $this->container->get("request_stack"),
            $this->container->get("event_dispatcher"),
            $this->container->get("thelia.securityContext"),
            $this->container->get("thelia.translator"),
            []
        );
    }
}
