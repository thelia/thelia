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

namespace Thelia\Core\Event\Module;

/**
 * Class ModuleDeleteEvent
 * @package Thelia\Core\Event\Module
 * @author Manuel Raynaud <manu@raynaud.io>
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
