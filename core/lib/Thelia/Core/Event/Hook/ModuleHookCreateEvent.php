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

namespace Thelia\Core\Event\Hook;

/**
 * Class ModuleHookCreateEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookCreateEvent extends ModuleHookEvent
{
    /** @var int */
    protected $module_id;

    /** @var int */
    protected $hook_id;

    /** @var string */
    protected $method;

    /** @var string */
    protected $classname;

    /** @var string */
    protected $templates;

    /**
     * @param int $hook_id
     * @return $this
     */
    public function setHookId($hook_id)
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getHookId()
    {
        return $this->hook_id;
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

    /**
     * @param string $classname
     * @return $this
     */
    public function setClassname($classname)
    {
        $this->classname = $classname;

        return $this;
    }

    /**
     * @return string
     */
    public function getClassname()
    {
        return $this->classname;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * @param string $templates
     * @return $this
     */
    public function setTemplates($templates)
    {
        $this->templates = $templates;

        return $this;
    }
}
