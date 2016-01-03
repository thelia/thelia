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
 * Class ModuleToggleActivationEvent
 * @package Thelia\Core\Event\Module
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleToggleActivationEvent extends ModuleEvent
{
    /**
     * @var int
     */
    protected $module_id;

    /**
     * @var bool
     */
    protected $noCheck;

    /**
     * @var bool
     */
    protected $recursive;


    /**
     * @param int $module_id
     */
    public function __construct($module_id)
    {
        $this->module_id = $module_id;
    }

    /**
     * @param int $module_id
     *
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

    /**
     * @return boolean
     */
    public function isNoCheck()
    {
        return $this->noCheck;
    }

    /**
     * @param boolean $noCheck
     * @return $this;
     */
    public function setNoCheck($noCheck)
    {
        $this->noCheck = $noCheck;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isRecursive()
    {
        return $this->recursive;
    }

    /**
     * @param boolean $recursive
     * @return $this;
     */
    public function setRecursive($recursive)
    {
        $this->recursive = $recursive;
        return $this;
    }
}
