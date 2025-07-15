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

namespace Thelia\Core\Event\Module;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Module;
use Thelia\Module\Validator\ModuleDefinition;

/**
 * Class ModuleEvent.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleInstallEvent extends ActionEvent
{
    protected ModuleDefinition $moduleDefinition;
    protected string $modulePath;

    public function __construct(protected ?Module $module = null)
    {
    }

    public function setModule(Module $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function hasModule(): bool
    {
        return $this->module instanceof Module;
    }

    /**
     * @return $this
     */
    public function setModuleDefinition(ModuleDefinition $moduleDefinition): self
    {
        $this->moduleDefinition = $moduleDefinition;

        return $this;
    }

    public function getModuleDefinition(): ModuleDefinition
    {
        return $this->moduleDefinition;
    }

    /**
     * @return $this
     */
    public function setModulePath(string $modulePath): self
    {
        $this->modulePath = $modulePath;

        return $this;
    }

    public function getModulePath(): string
    {
        return $this->modulePath;
    }
}
