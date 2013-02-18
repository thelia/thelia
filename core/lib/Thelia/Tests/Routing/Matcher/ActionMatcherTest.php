<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Routing\Matcher;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Routing\Matcher\ActionMatcher;
use Thelia\Core\Event\ActionEvent;

class ActionMatcherTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * 
     * if there is no action parameter and n
     * 
     * @expectedException Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @expectedExceptionMessage No action parameter found
     */
    public function testDispatchActionWithoutAction()
    {
        $actionMatcher = new ActionMatcher();
        
        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
        
        $dispatcher->expects($this->any())
                ->method("dispatch");
        $actionMatcher->setDispatcher($dispatcher);
        $actionMatcher->matchRequest(new Request());
    }

    public function testDispatchActionWithAddProduct()
    {
        $request = new Request(array(
            "action" => "addProduct"
        ));
        
        $actionMatcher = new ActionMatcher();
        $actionMatcher->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));
        $action = $actionMatcher->matchRequest($request);
        
        $this->assertArrayHasKey("_controller", $action);
        $this->assertInstanceOf("Thelia\Action\Cart", $action["_controller"][0]);
        
    }

}