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

namespace Thelia\Core\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
/**
 * 
 * Class thrown on Thelia.action event
 * 
 * call setAction if action match yours
 * 
 */
class ActionEvent extends Event
{
    
    /**
     *
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request;
    
    /**
     *
     * @var string
     */
    protected $action;
    
    /**
     *
     * @var string
     */
    protected $controller;
    
    /**
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $action
     */
    public function __construct(Request $request, $action) {
        $this->request = $request;
        $this->action = $action;
    }
    
    /**
     * 
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }
    
    /**
     * 
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }
    
    /**
     * 
     * @param string $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }
    
    public function getController()
    {
        return $this->controller;
    }
    
    public function hasController()
    {
        return null !== $this->controller;
    }
    
    
}
