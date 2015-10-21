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

namespace Thelia\Tests\Rewriting;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Model\RewritingUrl;
use Thelia\Rewriting\RewritingRetriever;
use Thelia\Tools\URL;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class RewritingRetrieverTest extends \PHPUnit_Framework_TestCase
{
    protected $container = null;

    public function setUp()
    {
        $this->container = new ContainerBuilder();

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

        $this->container->set('router.admin', $stubRouterAdmin);
        $this->container->set('thelia.url.manager', new URL($this->container));
    }

    protected function getMethod($name)
    {
        $class = new \ReflectionClass('\Thelia\Rewriting\RewritingRetriever');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function getProperty($name)
    {
        $class = new \ReflectionClass('\Thelia\Rewriting\RewritingRetriever');
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    public function testLoadViewUrl()
    {
        $searchResult = new RewritingUrl();
        $searchResult->setUrl('foo.html');

        $retrieverQuery = $this->getMock('\Thelia\Model\RewritingUrlQuery', array('getViewUrlQuery'));
        $retrieverQuery->expects($this->any())
            ->method('getViewUrlQuery')
            ->with('view', 'fr_FR', 1)
            ->will($this->returnValue($searchResult));

        $retriever = new RewritingRetriever();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($retriever, $retrieverQuery);

        $retriever->loadViewUrl('view', 'fr_FR', 1);

        $this->assertEquals(URL::getInstance()->absoluteUrl('foo.html'), $retriever->rewrittenUrl);
        $this->assertEquals(URL::getInstance()->viewUrl('view', array('lang' => 'fr_FR', 'view_id' => 1)), $retriever->url);
    }

    public function testLoadSpecificUrl()
    {
        $searchResult = new RewritingUrl();
        $searchResult->setUrl('foo.html');

        $retrieverQuery = $this->getMock('\Thelia\Model\RewritingUrlQuery', array('getSpecificUrlQuery'));
        $retrieverQuery->expects($this->any())
            ->method('getSpecificUrlQuery')
            ->with('view', 'fr_FR', 1, array('foo0' => 'bar0', 'foo1' => 'bar1'))
            ->will($this->returnValue($searchResult));

        $retriever = new RewritingRetriever();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($retriever, $retrieverQuery);

        $retriever->loadSpecificUrl('view', 'fr_FR', 1, array('foo0' => 'bar0', 'foo1' => 'bar1'));

        $this->assertEquals('foo.html', $retriever->rewrittenUrl);
        $this->assertEquals(URL::getInstance()->viewUrl('view', array('foo0' => 'bar0', 'foo1' => 'bar1', 'lang' => 'fr_FR', 'view_id' => 1)), $retriever->url);
    }
}
