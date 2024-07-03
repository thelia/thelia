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

namespace TheliaSmarty\Tests\Template\Plugin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\Controller\ControllerResolver;
use TheliaSmarty\Template\Plugins\Render;

/**
 * Class RenderTest.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class RenderTest extends SmartyPluginTestCase
{
    public function testRenderWithoutParams(): void
    {
        $data = $this->render('test.html');

        $this->assertEquals('Hello, world!', $data);
    }

    public function testRenderWithParams(): void
    {
        $data = $this->render('testParams.html');

        $this->assertEquals('Hello, world!', $data);
    }

    public function testMethodParameter(): void
    {
        $data = $this->render('testMethod.html');

        $this->assertEquals('PUT', $data);
    }

    public function testQueryArrayParamater(): void
    {
        $this->smarty->assign('query', ['foo' => 'bar']);
        $data = $this->render('testQueryArray.html');

        $this->assertEquals('bar', $data);
    }

    public function testQueryStringParamater(): void
    {
        $data = $this->render('testQueryString.html');

        $this->assertEquals('bar', $data);
    }

    public function testRequestParamater(): void
    {
        $data = $this->render('testRequest.html');

        $this->assertEquals('barPOSTbazPUT', $data);
    }

    /**
     * @return \TheliaSmarty\Template\AbstractSmartyPlugin
     */
    protected function getPlugin(ContainerBuilder $container)
    {
        return new Render(
            new ControllerResolver($container),
            $container->get('request_stack'),
            $container
        );
    }
}
