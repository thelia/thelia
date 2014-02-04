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

namespace Thelia\Tests\Type;

use Thelia\Tools\URL;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Franck Allimant <eroudeix@openstudio.fr>
 *
 */
class URLTest extends \PHPUnit_Framework_TestCase
{
    protected $context;

    public function setUp()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $router = $this->getMockBuilder("Symfony\Component\Routing\Router")
            ->disableOriginalConstructor()
            ->getMock();

        $this->context = new \Symfony\Component\Routing\RequestContext(
                '/thelia/index.php',
                'GET',
                'localhost',
                'http',
                80,
                443,
                '/path/to/action'
        );

        $router->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->context));

        $container->set("router.admin", $router);

        new URL($container);
    }

    public function testGetIndexPage()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->getIndexPage();
        $this->assertEquals('http://localhost/thelia/index.php', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->getIndexPage();
        $this->assertEquals('http://localhost/thelia/', $url);

        $this->context->setBaseUrl('/thelia');
        $url = URL::getInstance()->getIndexPage();
        $this->assertEquals('http://localhost/thelia', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->getIndexPage();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testGetBaseUrl()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->getBaseUrl();
        $this->assertEquals('http://localhost/thelia/index.php', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->getBaseUrl();
        $this->assertEquals('http://localhost/thelia/', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->getBaseUrl();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testAbsoluteUrl()
    {
        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action');
        $this->assertEquals('http://localhost/path/to/action', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action');
        $this->assertEquals('http://localhost/thelia/path/to/action', $url);

        $this->context->setBaseUrl('/thelia');
        $url = URL::getInstance()->absoluteUrl('/path/to/action');
        $this->assertEquals('http://localhost/thelia/path/to/action', $url);

        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/path/to/action');
        $this->assertEquals('http://localhost/thelia/index.php/path/to/action', $url);
    }

    public function testAbsoluteUrlOnAbsolutePath()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action');
        $this->assertEquals('http://myhost/path/to/action', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action');
        $this->assertEquals('http://myhost/path/to/action', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action');
        $this->assertEquals('http://myhost/path/to/action', $url);
    }

    public function testAbsoluteUrlOnAbsolutePathWithParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p1=v1&p2=v2', $url);
    }

    public function testAbsoluteUrlOnAbsolutePathWithParametersAddParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action?p0=v0', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p0=v0&p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action?p0=v0', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p0=v0&p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('http://myhost/path/to/action?p0=v0', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://myhost/path/to/action?p0=v0&p1=v1&p2=v2', $url);
    }

    public function testAbsoluteUrlWithParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/index.php/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia');
        $url = URL::getInstance()->absoluteUrl('path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/path/to/action?p1=v1&p2=v2', $url);
    }

    public function testAbsoluteUrlPathOnly()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array(), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/thelia/path/to/action', $url);
    }

    public function testAbsoluteUrlPathOnlyWithParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/thelia/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/thelia/path/to/action?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('/path/to/action', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/path/to/action?p1=v1&p2=v2', $url);

    }

    public function testAbsoluteUrlFromIndexWithParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/index.php/?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/thelia/?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"));
        $this->assertEquals('http://localhost/?p1=v1&p2=v2', $url);

    }

    public function testAbsoluteUrlPathOnlyFromIndexWithParameters()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/thelia/?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/thelia/');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/thelia/?p1=v1&p2=v2', $url);

        $this->context->setBaseUrl('/');
        $url = URL::getInstance()->absoluteUrl('/', array("p1" => "v1", "p2" => "v2"), URL::PATH_TO_FILE);
        $this->assertEquals('http://localhost/?p1=v1&p2=v2', $url);

    }

    public function testAbsoluteUrlPathWithParameterReplacement()
    {
        $this->context->setBaseUrl('/thelia/index.php');
        $url = URL::getInstance()->absoluteUrl(
            'http://localhost/index_dev.php/admin/categories/update?category_id=1&current_tab=general&folder_id=0',
            array("category_id" => "2", "edit_language_id" => "1", "current_tab" => "general"),
            URL::PATH_TO_FILE
        );
        $this->assertEquals('http://localhost/index_dev.php/admin/categories/update?folder_id=0&category_id=2&edit_language_id=1&current_tab=general', $url);

        $url = URL::getInstance()->absoluteUrl(
            'http://localhost/index_dev.php/admin/categories/update?category_id=1&current_tab=general&folder_id=0',
            array("edit_language_id" => "1"),
            URL::PATH_TO_FILE
        );
        $this->assertEquals('http://localhost/index_dev.php/admin/categories/update?category_id=1&current_tab=general&folder_id=0&edit_language_id=1', $url);

        $url = URL::getInstance()->absoluteUrl(
            'http://localhost/index_dev.php/admin/categories/update?category_id=1&current_tab=general&folder_id=0',
            array(),
            URL::PATH_TO_FILE
        );
        $this->assertEquals('http://localhost/index_dev.php/admin/categories/update?category_id=1&current_tab=general&folder_id=0', $url);

        $url = URL::getInstance()->absoluteUrl(
            'http://localhost/index_dev.php/admin/categories/update?category_id=1',
            array("category_id" => "2", "edit_language_id" => "1", "current_tab" => "general"),
            URL::PATH_TO_FILE
        );
        $this->assertEquals('http://localhost/index_dev.php/admin/categories/update?category_id=2&edit_language_id=1&current_tab=general', $url);
    }

    public function testRetrieve()
    {

    }
}
