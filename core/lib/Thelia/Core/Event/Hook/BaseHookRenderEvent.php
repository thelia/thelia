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

namespace Thelia\Core\Event\Hook;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class BaseHookRenderEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BaseHookRenderEvent extends Event
{
    /** @var  string $code the code of the hook */
    protected $code;

    /** @var  array $arguments an array of arguments passed to the template engine function */
    protected $arguments = [];

    /** @var array $templateVars the variable currently defined in the template */
    protected $templateVars = [];

    public function __construct($code, array $arguments = [], array $templateVars = [])
    {
        $this->code = $code;
        $this->arguments = $arguments;
        $this->templateVars = $templateVars;
    }

    /**
     * Set the code of the hook
     *
     * @param  string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get the code of the hook
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set all arguments
     *
     * @param  array $arguments
     * @return $this
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Get all arguments
     *
     * @return array all arguments
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Get all template vars
     *
     * @return array all template vars
     */
    public function getTemplateVars()
    {
        return $this->templateVars;
    }

    /**
     * add or replace an argument
     *
     * @param  string $key
     * @param  string $value
     * @return $this
     */
    public function setArgument($key, $value)
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    /**
     * Get an argument
     * @param  string $key
     * @param  string|null $default
     * @return mixed|null  the value of the argument or `$default` if it not exists
     */
    public function getArgument($key, $default = null)
    {
        return \array_key_exists($key, $this->arguments) ? $this->arguments[$key] : $default;
    }

    /**
     * Check if an argument exists with this key
     *
     * @param $key
     * @return bool true if it exists, else false
     */
    public function hasArgument($key)
    {
        return \array_key_exists($key, $this->arguments);
    }

    /**
     * Return a template variable value. An exception is thorwn if the variable is not defined.
     *
     * @param string $templateVariableName the variable name
     *
     * @return mixed the variable value
     * @throws \InvalidArgumentException if the variable is not defined
     */
    public function getTemplateVar($templateVariableName)
    {
        if (! isset($this->templateVars[$templateVariableName])) {
            throw new \InvalidArgumentException(sprintf("Template variable '%s' is not defined.", $templateVariableName));
        }

        return $this->templateVars[$templateVariableName];
    }

    /**
     * Check if a template variable is defined.
     *
     * @param $templateVariableName
     *
     * @return bool true if the template variable is defined, false otherwise
     */
    public function hasTemplateVar($templateVariableName)
    {
        return isset($this->templateVars[$templateVariableName]);
    }
}
