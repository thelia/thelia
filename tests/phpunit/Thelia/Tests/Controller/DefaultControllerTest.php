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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Controller\Front\DefaultController;

/**
 * Class DefaultControllerTest
 * @package Thelia\Tests\Controller
 * @author Manuel Raynaud <manu@raynaud.io>
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
