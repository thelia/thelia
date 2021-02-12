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

namespace Thelia\Core\Event\Module;

/**
 * Class ModuleDeleteEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleDeleteEvent extends ModuleEvent
{
    protected $module_id;
    protected $delete_data;
    protected $assume_delete;

    public function __construct(int $module_id, bool $assume_delete = false)
    {
        $this->module_id = $module_id;
        $this->assume_delete = $assume_delete;
    }

    public function setModuleId(int $module_id): void
    {
        $this->module_id = $module_id;
    }

    public function getModuleId(): int
    {
        return $this->module_id;
    }

    public function getDeleteData(): bool
    {
        return $this->delete_data;
    }

    public function setDeleteData(bool $delete_data): self
    {
        $this->delete_data = $delete_data;

        return $this;
    }

    public function getAssumeDelete(): bool
    {
        return $this->assume_delete;
    }

    public function setAssumeDelete(bool $assume_delete): self
    {
        $this->assume_delete = $assume_delete;

        return $this;
    }
}
