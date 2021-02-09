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

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\RewritingArgument;
use Thelia\Rewriting\RewritingResolver;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class RewritingResolverTest extends TestCase
{
    protected function getMethod($name)
    {
        $class = new \ReflectionClass('\Thelia\Rewriting\RewritingResolver');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function getProperty($name)
    {
        $class = new \ReflectionClass('\Thelia\Rewriting\RewritingResolver');
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    public function testGetOtherParametersException()
    {
        $resolver = new RewritingResolver();

        $this->expectException(\Thelia\Exception\UrlRewritingException::class);
        $this->expectExceptionCode(800);
        $method = $this->getMethod('getOtherParameters');
        $method->invoke($resolver);
    }

    public function testGetOtherParameters()
    {
        $rewritingArguments = [
            ['Parameter' => 'foo0', 'Value' => 'bar0'],
            ['Parameter' => 'foo1', 'Value' => 'bar1'],
            ['Parameter' => 'foo2', 'Value' => 'bar2'],
        ];
        $searchResult = new ObjectCollection();
        $searchResult->setModel('\Thelia\Model\RewritingArgument');
        $searchResult->fromArray($rewritingArguments);

        $resolver = new RewritingResolver();

        $search = $this->getProperty('search');
        $search->setValue($resolver, $searchResult);

        $method = $this->getMethod('getOtherParameters');
        $actual = $method->invoke($resolver);

        $expected = [
            'foo0' => 'bar0',
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testLoadException()
    {
        $collection = new ObjectCollection();
        $collection->setModel('\Thelia\Model\RewritingArgument');

        $resolverQuery = $this->createMock('\Thelia\Model\RewritingUrlQuery');
        $resolverQuery->expects($this->any())
            ->method('getResolverSearch')
            ->with('foo.html')
            ->will($this->returnValue($collection));

        $resolver = new RewritingResolver();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($resolver, $resolverQuery);

        $this->expectException(\Thelia\Exception\UrlRewritingException::class);
        $this->expectExceptionCode(404);
        $resolver->load('foo.html');
    }

    public function testLoad()
    {
        $collection = new ObjectCollection();
        $collection->setModel('\Thelia\Model\RewritingArgument');

        for ($i=0; $i<3; $i++) {
            $ra = new RewritingArgument();
            $ra->setParameter('foo' . $i);
            $ra->setValue('bar' . $i);
            $ra->setVirtualColumn('ru_view', 'view');
            $ra->setVirtualColumn('ru_viewId', 'viewId');
            $ra->setVirtualColumn('ru_locale', 'locale');
            $ra->setVirtualColumn('ru_redirected_to_url', null);

            $collection->append($ra);
        }

        $resolverQuery = $this->createMock('\Thelia\Model\RewritingUrlQuery');
        $resolverQuery->expects($this->any())
            ->method('getResolverSearch')
            ->with('foo.html')
            ->will($this->returnValue($collection));

        $resolver = new RewritingResolver();

        $rewritingUrlQuery = $this->getProperty('rewritingUrlQuery');
        $rewritingUrlQuery->setValue($resolver, $resolverQuery);

        $resolver->load('foo.html');

        $this->assertEquals('view', $resolver->view);
        $this->assertEquals('viewId', $resolver->viewId);
        $this->assertEquals('locale', $resolver->locale);
        $this->assertEquals(['foo0' => 'bar0', 'foo1' => 'bar1', 'foo2' => 'bar2'], $resolver->otherParameters);
    }
}
