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

namespace Thelia\Core\Event\Module;

/**
 * Class ModuleDeleteEvent
 * @package Thelia\Core\Event\Module
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ModuleDeleteEvent extends ModuleEvent
{
    /**
     * @var int module id
     */
    protected $module_id;
    protected $delete_data;

    public function __construct($module_id)
    {
        $this->module_id = $module_id;
    }

    /**
     * @param int $module_id
     */
    public function setModuleId($module_id)
    {
        $this->module_id = $module_id;
    }

    /**
     * @return int
     */
    public function getModuleId()
    {
        return $this->module_id;
    }

    public function getDeleteData()
    {
        return $this->delete_data;
    }

    public function setDeleteData($delete_data)
    {
        $this->delete_data = $delete_data;

        return $this;
    }
}
