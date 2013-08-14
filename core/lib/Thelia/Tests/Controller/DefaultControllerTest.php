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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\DefaultController;

class DefaultControllerTest extends \PHPUnit_Framework_TestCase
{

    public function testNoAction()
    {
        $defaultController = new DefaultController();
        $request = new Request();
        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), "index");
    }

    public function testNoActionWithQuery()
    {
        $defaultController = new DefaultController();
        $request = new Request(array(
            "view" => "foo"
        ));

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }

    public function testNoActionWithRequest()
    {
        $defaultController = new DefaultController();
        $request = new Request(array(), array(
            "view" => "foo"
        ));

        $defaultController->noAction($request);

        $this->assertEquals($request->attributes->get('_view'), 'foo');
    }
}
