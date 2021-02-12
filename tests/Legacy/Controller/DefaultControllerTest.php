<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Controller;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Controller\Front\DefaultController;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class DefaultControllerTest.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class DefaultControllerTest extends ControllerTestBase
{
    public function testNoAction(): void
    {
        $request = $this->buildRequest();
        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'index');
    }

    public function testNoActionWithGetParam(): void
    {
        $request = $this->buildRequest(
            ['view' => 'foo']
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithPostParam(): void
    {
        $request = $this->buildRequest(
            [],
            ['view' => 'foo']
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithAttribute(): void
    {
        $request = $this->buildRequest(
            [],
            [],
            ['_view' => 'foo']
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithAttributeAndQuery(): void
    {
        $request = $this->buildRequest(
            ['view' => 'bar'],
            [],
            ['_view' => 'foo']
        );

        $this->getController()->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'bar');
    }

    public function testNoActionWithAttributeAndRequest(): void
    {
        $request = $this->buildRequest(
            [],
            ['view' => 'bar'],
            ['_view' => 'foo']
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
    protected function buildContainer(ContainerBuilder $container): void
    {
        $parser = $this->getMockBuilder('Thelia\\Core\\Template\\ParserInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $container->set('thelia.parser', $parser);
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
