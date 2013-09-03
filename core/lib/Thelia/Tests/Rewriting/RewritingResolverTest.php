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

namespace Thelia\Tests\Rewriting;

use Thelia\Model\RewritingArgument;
use Thelia\Rewriting\RewritingResolver;
use Propel\Runtime\Collection\ObjectCollection;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class RewritingResolverTest extends \PHPUnit_Framework_TestCase
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

    public function testGetOtherParameters()
    {
        $rewritingArguments = array(
            array('Parameter' => 'foo0', 'Value' => 'bar0'),
            array('Parameter' => 'foo1', 'Value' => 'bar1'),
            array('Parameter' => 'foo2', 'Value' => 'bar2'),
        );
        $searchResult = new ObjectCollection();
        $searchResult->setModel('\Thelia\Model\RewritingArgument');
        $searchResult->fromArray($rewritingArguments);

        $resolver = new RewritingResolver();

        $search = $this->getProperty('search');
        $search->setValue($resolver, $searchResult);

        $method = $this->getMethod('getOtherParameters');
        $actual = $method->invoke($resolver);

        $expected = array(
            'foo0' => 'bar0',
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        );

        $this->assertEquals($expected, $actual);
    }

    public function testLoad()
    {
        $collection = new ObjectCollection();
        $collection->setModel('\Thelia\Model\RewritingArgument');

        for($i=0; $i<3; $i++) {
            $ra = new RewritingArgument();
            $ra->setParameter('foo' . $i);
            $ra->setValue('bar' . $i);
            $ra->setVirtualColumn('ru_view', 'view');
            $ra->setVirtualColumn('ru_viewId', 'viewId');
            $ra->setVirtualColumn('ru_locale', 'locale');
            $ra->setVirtualColumn('ru_redirected_to_url', null);

            $collection->append($ra);
        }


        $resolverQuery = $this->getMock('\Thelia\Model\RewritingUrlQuery', array('getResolverSearch'));
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
        $this->assertEquals(array('foo0' => 'bar0', 'foo1' => 'bar1', 'foo2' => 'bar2'), $resolver->otherParameters);
    }
}
