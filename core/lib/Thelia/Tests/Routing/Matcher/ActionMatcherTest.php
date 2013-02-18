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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;

class ActionMatcherTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @expectedException Symfony\Component\Routing\Exception\ResourceNotFoundException
     */
    public function testDispatchActionWithoutAction()
    {
        $actionMatcher = new ActionMatcher();
        $request = new Request();
        
        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
        
        $dispatcher->expects($this->any())
                ->method("dispatch");
        $actionMatcher->setDispatcher($dispatcher);
        $actionMatcher->matchRequest($request);
    }

}