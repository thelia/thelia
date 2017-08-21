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

    /**
     * @var bool
     */
    protected $assume_delete;

    public function __construct($module_id, $assume_delete = false)
    {
        parent::__construct();

        $this->module_id = $module_id;
        $this->assume_delete = $assume_delete;
    }

    /**
     * @param int $module_id
     * @return $this
     */
    public function setModuleId($module_id)
    {
        $this->module_id = $module_id;

        return $this;
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


    /**
     * @param boolean $delete_data
     * @return $this
     */
    public function setDeleteData($delete_data)
    {
        $this->delete_data = $delete_data;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAssumeDelete()
    {
        return $this->assume_delete;
    }

    /**
     * @param boolean $assume_delete
     * @return $this
     */
    public function setAssumeDelete($assume_delete)
    {
        $this->assume_delete = $assume_delete;
        return $this;
    }
}
