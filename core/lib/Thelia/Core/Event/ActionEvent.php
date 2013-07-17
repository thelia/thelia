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
use Symfony\Component\HttpFoundation\Request;
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

    protected $form = null;

    /**
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string                                    $action
     */
    public function __construct(Request $request, $action)
    {
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
     * Store a form taht contains error, to pass it to the current session.
     *
     * @param BaseForm $form
     */
    public function setFormError(BaseForm $form)
    {
        $this->form = $form;
        $this->stopPropagation();
    }

    /**
     * @return BaseForm the errored form, or null
     */
    public function getFormError()
    {
        return $this->form;
    }

    /**
     * Check if theis event contains a form with errors
     *
     * @return boolean
     */
    public function hasFormError()
    {
        return $this->form !== null;
    }
}
