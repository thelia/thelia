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
    /**
     * @var bool
     */
    protected $noCheck;

    /**
     * @var bool
     */
    protected $recursive;

    /**
     * @param int $module_id
     * @param bool $assume_deactivate
     */
    public function __construct(protected $module_id, protected $assume_deactivate = false)
    {
    }

    /**
     * @param int $module_id
     *
     * @return $this
     */
    public function setModuleId($module_id): self
    {
        $this->module_id = $module_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getModuleId()
    {
        return $this->module_id;
    }

    /**
     * @return bool
     */
    public function isNoCheck()
    {
        return $this->noCheck;
    }

    /**
     * @param bool $noCheck
     *
     * @return $this;
     */
    public function setNoCheck($noCheck): self
    {
        $this->noCheck = $noCheck;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRecursive()
    {
        return $this->recursive;
    }

    /**
     * @param bool $recursive
     *
     * @return $this;
     */
    public function setRecursive($recursive): self
    {
        $this->recursive = $recursive;

        return $this;
    }

    /**
     * @return bool
     */
    public function getAssumeDeactivate()
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
