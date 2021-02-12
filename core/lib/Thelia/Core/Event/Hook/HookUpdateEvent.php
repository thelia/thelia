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

namespace Thelia\Core\Event\Hook;

/**
 * Class HookUpdateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookUpdateEvent extends HookCreateEvent
{
    protected $hook_id;
    protected $by_module;
    protected $block;
    protected $chapo;
    protected $description;

    /**
     * @param int $hook_id
     */
    public function __construct($hook_id)
    {
        $this->hook_id = $hook_id;
    }

    /**
     * @param int $hook_id
     *
     * @return $this
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

    public function setBlock($block)
    {
        $this->block = $block;

        return $this;
    }

    public function getBlock()
    {
        return $this->block;
    }

    public function setByModule($by_module)
    {
        $this->by_module = $by_module;

        return $this;
    }

    public function getByModule()
    {
        return $this->by_module;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }
}
