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
 * Class ModuleHookUpdateEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookUpdateEvent extends ModuleHookCreateEvent
{
    protected $module_hook_id;
    protected $active;

    /**
     * @param int $module_hook_id
     * @return $this
     */
    public function setModuleHookId($module_hook_id)
    {
        $this->module_hook_id = $module_hook_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getModuleHookId()
    {
        return $this->module_hook_id;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }
}
