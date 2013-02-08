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

namespace Thelia\Routing\Matcher;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Action\Cart;

/**
 * Matcher using action param in get or post method to perform actions. For exemple index.php?action=addCart will find the good controller that can perform this action.
 * 
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ActionMatcher implements RequestMatcherInterface
{
    /**
     *
     * @var Symfony\Component\EventDispatcher\EventDispatcherInterface 
     */
    protected $dispatcher;
    
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    
    public function matchRequest(Request $request)
    {
        if (false !== $action = $request->get("action")) {
            //search corresponding action
            return $this->dispatchAction($request, $action);
        }
        
        throw new ResourceNotFoundException("No action parameter found");
    }
    
    protected function dispatchAction(Request $request, $action)
    {
        $controller = null;
        switch ($action) {
            case 'addProduct':
                $controller = array(
                    new Cart($this->dispatcher),
                    "addCart"
                );
                break;
            default : 
                $event = new ActionEvent($request, $action);
                $this->dispatcher->dispatch(TheliaEvents::ACTION, $event);
                if ($event->hasController()) {
                    $controller = $event->getController();
                }
                break;
        }
        
        if ($controller) {
            return array(
                '_controller' => $controller
            );
        }
        
        throw new ResourceNotFoundException("No action parameter found");
        
    }
}
