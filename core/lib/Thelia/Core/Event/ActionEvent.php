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

namespace Thelia\Core\Event;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Form\BaseForm;
use Thelia\Core\Security\SecurityContext;
/**
 *
 * Class thrown on Thelia.action event
 *
 * call setAction if action match yours
 *
 */
abstract class ActionEvent extends Event
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

    protected $errorForm = null;

    protected $parameters = array();

    /**
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string                                    $action
     */
    public function __construct(Request $request, $action)
    {
        $this->request = $request;
        $this->action  = $action;
    }


    public function __set($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        return null;
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

    public function setErrorForm(BaseForm $form) {
    	$this->errorForm = $form;

    	if ($form != null) $this->stopPropagation();
    }

    public function getErrorForm() {
    	return $this->errorForm;
    }

    public function hasErrorForm() {
    	return $this->errorForm != null ? true : false;
    }
}
