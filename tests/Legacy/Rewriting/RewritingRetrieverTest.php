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

namespace Thelia\Tests\Rewriting;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Model\RewritingUrl;
use Thelia\Service\Rewriting\RewritingRetriever;
use Thelia\Tools\URL;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class RewritingRetrieverTest extends TestCase
{
    protected $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

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
            ->willReturn('localhost');

        $stubRouterAdmin->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $stubRequestContext
            );

        $this->container->set('router.admin', $stubRouterAdmin);
        $this->container->set('thelia.url.manager', new URL($stubRouterAdmin));
    }

    protected function getMethod($name)
    {
        $class = new \ReflectionClass('\Thelia\Service\Rewriting\RewritingRetriever');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function getProperty($name)
    {
        $class = new \ReflectionClass('\Thelia\Service\Rewriting\RewritingRetriever');
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    public function testLoadViewUrl(): void
    {
        $searchResult = new RewritingUrl();
        $searchResult->setUrl('foo.html');

        $retrieverQuery = $this->createMock('\Thelia\Model\RewritingUrlQuery');
        $retrieverQuery->expects($this->any())
            ->method('getViewUrlQuery')
            ->with('view', 'fr_FR', 1)
            ->willReturn($searchResult);

        $retriever = new RewritingRetriever();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($retriever, $retrieverQuery);

        $retriever->loadViewUrl('view', 'fr_FR', 1);

        $this->assertEquals(URL::getInstance()->absoluteUrl('foo.html'), $retriever->rewrittenUrl);
        $this->assertEquals(URL::getInstance()->viewUrl('view', ['lang' => 'fr_FR', 'view_id' => 1]), $retriever->url);
    }

    public function testLoadSpecificUrl(): void
    {
        $searchResult = new RewritingUrl();
        $searchResult->setUrl('foo.html');

        $retrieverQuery = $this->createMock('\Thelia\Model\RewritingUrlQuery');
        $retrieverQuery->expects($this->any())
            ->method('getSpecificUrlQuery')
            ->with('view', 'fr_FR', 1, ['foo0' => 'bar0', 'foo1' => 'bar1'])
            ->willReturn($searchResult);

        $retriever = new RewritingRetriever();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($retriever, $retrieverQuery);

        $retriever->loadSpecificUrl('view', 'fr_FR', 1, ['foo0' => 'bar0', 'foo1' => 'bar1']);

        $this->assertEquals('foo.html', $retriever->rewrittenUrl);
        $this->assertEquals(URL::getInstance()->viewUrl('view', ['foo0' => 'bar0', 'foo1' => 'bar1', 'lang' => 'fr_FR', 'view_id' => 1]), $retriever->url);
    }
}
