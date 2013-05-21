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

namespace Thelia\Core\Factory;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEventClass;

class ActionEventFactory
{

    protected $request;
    protected $action;
    protected $dispatcher;

    public function __construct(Request $request, $action, EventDispatcherInterface $dispatcher)
    {
        $this->request = $request;
        $this->action = $action;
        $this->dispatcher = $dispatcher;
    }

    public function createActionEvent()
    {
        $className = "Thelia\\Core\\Event\\".$this->action."Event";
        $class = null;
        if (class_exists($className)) {
            $class = new \ReflectionClass($className);
            // return $class->newInstance($this->request, $this->action);
        } else {
            $actionEventClass = new ActionEventClass($this->action);
            $this->dispatcher->dispatch("action.searchClass", $actionEventClass);

            if ($actionEventClass->hasClassName()) {
                $class = new \ReflectionClass($className);
            }
        }

        if( is_null($class)) {
            $class = new \ReflectionClass("Thelia\Core\Event\DefaultActionEvent");
        }

        if ($class->isSubclassOf("Thelia\Core\Event\ActionEvent") === false) {

        }

        return $class->newInstance($this->request, $this->action);
    }



}