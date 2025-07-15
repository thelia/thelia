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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ModuleHook;

/**
 * Class ModuleHookEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookEvent extends ActionEvent
{
    public function __construct(public ?ModuleHook $moduleHook = null)
    {
    }

    public function hasModuleHook(): bool
    {
        return $this->moduleHook instanceof ModuleHook;
    }

    public function getModuleHook(): ?ModuleHook
    {
        return $this->moduleHook;
    }

    public function setModuleHook(ModuleHook $moduleHook): static
    {
        $this->moduleHook = $moduleHook;

        return $this;
    }
}
