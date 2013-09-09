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

        $this->assertEquals('foo.html', $retriever->rewrittenUrl);
        $this->assertEquals(URL::getInstance()->viewUrl('view', array('locale' => 'fr_FR', 'view_id' => 1)), $retriever->url);
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
        $this->assertEquals(URL::getInstance()->viewUrl('view', array('foo0' => 'bar0', 'foo1' => 'bar1', 'locale' => 'fr_FR', 'view_id' => 1)), $retriever->url);
    }
}
