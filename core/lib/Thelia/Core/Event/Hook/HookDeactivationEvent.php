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
 * Class HookToggleActivationEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookDeactivationEvent extends HookEvent
{
    /** @var int */
    protected $hook_id;

    /**
     * @param int $hook_id
     */
    public function __construct($hook_id)
    {
        $this->hook_id = $hook_id;
    }

    /**
     * @param mixed $hook_id
     * @return $this
     */
    public function setHookId($hook_id)
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getHookId()
    {
        return $this->hook_id;
    }
}
