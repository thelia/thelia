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

/**
 * Class ModuleToggleActivationEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleToggleActivationEvent extends ModuleEvent
{
    protected bool $noCheck;
    protected bool $recursive;

    /**
     * @param int  $module_id
     * @param bool $assume_deactivate
     */
    public function __construct(protected $module_id, protected $assume_deactivate = false)
    {
    }

    /**
     * @return $this
     */
    public function setModuleId(int $module_id): self
    {
        $this->module_id = $module_id;

        return $this;
    }

    public function getModuleId(): int
    {
        return $this->module_id;
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
        return $this->assume_deactivate;
    }

    /**
     * @return $this;
     */
    public function setAssumeDeactivate($assume_deactivate): self
    {
        $this->assume_deactivate = $assume_deactivate;

        return $this;
    }
}
