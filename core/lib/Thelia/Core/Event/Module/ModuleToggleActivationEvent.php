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

use Thelia\Model\ModuleQuery;

/**
 * Class ModuleToggleActivationEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleToggleActivationEvent extends ModuleEvent
{
    protected bool $noCheck = true;
    protected bool $recursive = false;

    public function __construct(
        protected int $moduleId,
        protected bool $assumeDeactivate = false,
    ) {
        $module = ModuleQuery::create()->findPk($moduleId);
        if (null === $module) {
            throw new \InvalidArgumentException(\sprintf('Module with ID %d does not exist.', $moduleId));
        }
        parent::__construct($module);
    }

    /**
     * @return $this
     */
    public function setModuleId(int $moduleId): self
    {
        $this->moduleId = $moduleId;

        return $this;
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function isNoCheck(): bool
    {
        return $this->noCheck;
    }

    /**
     * @return $this;
     */
    public function setNoCheck(bool $noCheck): self
    {
        $this->noCheck = $noCheck;

        return $this;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }

    /**
     * @return $this;
     */
    public function setRecursive(bool $recursive): self
    {
        $this->recursive = $recursive;

        return $this;
    }

    public function getAssumeDeactivate(): bool
    {
        return $this->assumeDeactivate;
    }

    /**
     * @return $this;
     */
    public function setAssumeDeactivate($assumeDeactivate): self
    {
        $this->assumeDeactivate = $assumeDeactivate;

        return $this;
    }
}
