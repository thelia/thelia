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

namespace Thelia\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Controller\Admin\ProductController;
use Thelia\Controller\Front\DefaultController;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class DefaultControllerTest
 * @package Thelia\Tests\Controller
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class DefaultControllerTest extends ControllerTestBase
{
    public function testNoAction()
    {
        $request = $this->buildRequest();
        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), "index");
    }

    public function testNoActionWithGetParam()
    {
        $request = $this->buildRequest(
            ["view" => "foo"]
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithPostParam()
    {
        $request = $this->buildRequest(
            [],
            ["view" => "foo"]
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithAttribute()
    {
        $request = $this->buildRequest(
            [],
            [],
            ["_view" => "foo"]
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithAttributeAndQuery()
    {
        $request = $this->buildRequest(
            ["view" => "bar"],
            [],
            ["_view" => "foo"]
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'bar');
    }

    public function testNoActionWithAttributeAndRequest()
    {
        $request = $this->buildRequest(
            [],
            ["view" => "bar"],
            ["_view" => "foo"]
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'bar');
    }

    protected function buildRequest($query = [], $request = [], $attributes = []): Request
    {
        $request = new Request(
            $query, $request, $attributes
        );

        $request->setSession($this->getSession());

        return $request;
    }

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $parser = $this->getMockBuilder("Thelia\\Core\\Template\\ParserInterface")
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $container->set("thelia.parser", $parser);
    }

    /**
     * @return \Thelia\Controller\Front\DefaultController The controller you want to test
     */
    protected function getController(): DefaultController
    {
        $controller = new DefaultController();

        return $controller;
    }
}
