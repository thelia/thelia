<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Module;

/**
 * Class ModuleDeleteEvent.
 *
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
        $this->module_id = $module_id;
        $this->assume_delete = $assume_delete;
    }

    /**
     * @param int $module_id
     */
    public function setModuleId($module_id): void
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

    /**
     * @return bool
     */
    public function getAssumeDelete()
    {
        return $this->assume_delete;
    }

    /**
     * @return $this;
     */
    public function setAssumeDelete($assume_delete)
    {
        $this->assume_delete = $assume_delete;

        return $this;
    }
}
