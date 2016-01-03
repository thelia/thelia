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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Module;
use Thelia\Module\Validator\ModuleDefinition;

/**
 * Class ModuleEvent
 * @package Thelia\Core\Event\Module
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleInstallEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Module
     */
    protected $module;

    /** @var  ModuleDefinition $moduleDefinition */
    protected $moduleDefinition;

    /** @var  string $modulePath */
    protected $modulePath;

    public function __construct(Module $module = null)
    {
        $this->module = $module;
    }

    /**
     * @param \Thelia\Model\Module $module
     *
     * @return $this
     */
    public function setModule(Module $module)
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return \Thelia\Model\Module
     */
    public function getModule()
    {
        return $this->module;
    }

    public function hasModule()
    {
        return null !== $this->module;
    }

    /**
     * @param ModuleDefinition $moduleDefinition
     *
     * @return $this
     */
    public function setModuleDefinition($moduleDefinition)
    {
        $this->moduleDefinition = $moduleDefinition;

        return $this;
    }

    /**
     * @return ModuleDefinition
     */
    public function getModuleDefinition()
    {
        return $this->moduleDefinition;
    }

    /**
     * @param string $modulePath
     *
     * @return $this
     */
    public function setModulePath($modulePath)
    {
        $this->modulePath = $modulePath;

        return $this;
    }

    /**
     * @return string
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }
}
