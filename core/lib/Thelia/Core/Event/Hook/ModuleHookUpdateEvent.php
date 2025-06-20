<?php

declare(strict_types=1);

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
 * Class ModuleHookUpdateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookUpdateEvent extends ModuleHookCreateEvent
{
    protected $module_hook_id;

    protected $active;

    /**
     * @param int $module_hook_id
     *
     * @return $this
     */
    public function setModuleHookId($module_hook_id): static
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
     *
     * @return $this
     */
    public function setActive($active): static
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
