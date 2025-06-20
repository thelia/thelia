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
 * Class ModuleHookDeleteEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookDeleteEvent extends ModuleHookEvent
{
    /**
     * @param int $module_hook_id
     */
    public function __construct(protected $module_hook_id)
    {
    }

    /**
     * @return $this
     */
    public function setModuleHookId($module_hook_id): static
    {
        $this->module_hook_id = $module_hook_id;

        return $this;
    }

    public function getModuleHookId()
    {
        return $this->module_hook_id;
    }
}
