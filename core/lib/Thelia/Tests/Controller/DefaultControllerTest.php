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

namespace Thelia\Tests\Controller;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Controller\Front\DefaultController;

/**
 * Class DefaultControllerTest
 * @package Thelia\Tests\Controller
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class DefaultControllerTest extends \PHPUnit_Framework_TestCase
{

    public function testNoAction()
    {
        $defaultController = new DefaultController();
        $request = new Request();
        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), "index");
    }

    public function testNoActionWithGetParam()
    {
        $defaultController = new DefaultController();
        $request = new Request(array(
            "view" => "foo"
        ));

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithPostParam()
    {
        $defaultController = new DefaultController();
        $request = new Request(
            array(),
            array("view" => "foo")
        );

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }


    public function testNoActionWithAttribute()
    {
        $defaultController = new DefaultController();
        $request = new Request(
            array(),
            array(),
            array("_view" => "foo")
        );

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithAttributeAndQuery()
    {
        $defaultController = new DefaultController();
        $request = new Request(
            array("view" => "bar"),
            array(),
            array("_view" => "foo")
        );

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'bar');
    }

    public function testNoActionWithAttributeAndRequest()
    {
        $defaultController = new DefaultController();
        $request = new Request(
            array(),
            array("view" => "bar"),
            array("_view" => "foo")
        );

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'bar');
    }
}
