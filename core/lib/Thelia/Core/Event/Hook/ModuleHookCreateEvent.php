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
 * Class ModuleHookCreateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookCreateEvent extends ModuleHookEvent
{
    protected int $module_id;
    protected int $hook_id;
    protected string $method;
    protected string $classname;
    protected string $templates;

    /**
     * @return $this
     */
    public function setHookId(int $hook_id): static
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    public function getHookId(): int
    {
        return $this->hook_id;
    }

    /**
     * @return $this
     */
    public function setModuleId(int $module_id): static
    {
        $this->module_id = $module_id;

        return $this;
    }

    public function getModuleId(): int
    {
        return $this->module_id;
    }

    /**
     * @return $this
     */
    public function setClassname(string $classname = ''): static
    {
        $this->classname = $classname;

        return $this;
    }

    public function getClassname(): string
    {
        return $this->classname;
    }

    /**
     * @return $this
     */
    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getTemplates(): string
    {
        return $this->templates;
    }

    /**
     * @return $this
     */
    public function setTemplates(string $templates): static
    {
        $this->templates = $templates;

        return $this;
    }
}
