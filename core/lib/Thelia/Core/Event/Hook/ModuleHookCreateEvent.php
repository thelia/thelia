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
    /** @var int */
    protected $module_id;

    /** @var int */
    protected $hook_id;

    /** @var string */
    protected $method;

    /** @var string */
    protected $classname;

    /** @var string */
    protected $templates;

    /**
     * @param int $hook_id
     *
     * @return $this
     */
    public function setHookId($hook_id): static
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getHookId()
    {
        return $this->hook_id;
    }

    /**
     * @param int $module_id
     *
     * @return $this
     */
    public function setModuleId($module_id): static
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
     * @return $this
     */
    public function setClassname(string $classname = ''): static
    {
        $this->classname = $classname;

        return $this;
    }

    /**
     * @return string
     */
    public function getClassname()
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

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getTemplates()
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
