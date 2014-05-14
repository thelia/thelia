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
 * Class HookUpdateEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookUpdateEvent extends HookCreateEvent
{
    protected $hook_id;
    protected $chapo;
    protected $description;

    public function __construct($hook_id)
    {
        $this->hook_id = $hook_id;
    }

    /**
     * @param \Thelia\Model\Hook $hook_id
     */
    public function setHookId($hook_id)
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return \Thelia\Model\Hook
     */
    public function getHookId()
    {
        return $this->hook_id;
    }

    /**
     * @param mixed $chapo
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

}
