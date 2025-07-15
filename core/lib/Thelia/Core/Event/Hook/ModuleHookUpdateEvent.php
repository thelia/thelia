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
     * @return $this
     */
    public function setModuleHookId(int $module_hook_id): static
    {
        $this->module_hook_id = $module_hook_id;

        return $this;
    }

    public function getModuleHookId(): int
    {
        return $this->module_hook_id;
    }

    /**
     * @return $this
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }
}
