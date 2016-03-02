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

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class ContainerAwareTestCase
 * @package Thelia\Tests
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ContainerAwareTestCase extends \PHPUnit_Framework_TestCase
{
    protected $import;

    /** @var ContainerInterface */
    protected $container;

    /** @var  Session */
    protected $session;

    public function getContainer()
    {
        $container = new ContainerBuilder();
        $container->set("thelia.translator", new Translator(new Container()));

        $dispatcher = $this->getMockEventDispatcher();

        $container->set("event_dispatcher", $dispatcher);

        $request = new Request();
        $request->setSession($this->getSession());

        $container->set("request", $request);

        $requestStack = new RequestStack();

        $requestStack->push($request);

        $container->set("request_stack", $requestStack);

        new Translator($container);
        $container->set("thelia.securitycontext", new SecurityContext($requestStack));

        $this->buildContainer($container);

        return $container;
    }

    public function getSession()
    {
        return new Session(new MockArraySessionStorage());
    }

    public function setUp()
    {
        Tlog::getNewInstance();

        $this->session = $this->getSession();
        $this->container = $this->getContainer();
    }

    /**
     * @param ContainerBuilder $container
     * Use this method to build the container with the services that you need.
     */
    abstract protected function buildContainer(ContainerBuilder $container);

    /**
     * @return EventDispatcherInterface
     */
    protected function getMockEventDispatcher()
    {
        return $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    /**
     * @return KernelInterface
     */
    public function getKernel()
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }
}
