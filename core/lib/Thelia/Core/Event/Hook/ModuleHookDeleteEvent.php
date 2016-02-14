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
 * Class ModuleHookDeleteEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookDeleteEvent extends ModuleHookEvent
{
    /** @var int */
    protected $module_hook_id;

    /**
     * @param int $module_hook_id
     */
    public function __construct($module_hook_id)
    {
        $this->module_hook_id = $module_hook_id;
    }

    /**
     * @param mixed $module_hook_id
     * @return $this
     */
    public function setModuleHookId($module_hook_id)
    {
        $this->module_hook_id = $module_hook_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getModuleHookId()
    {
        return $this->module_hook_id;
    }
}
