<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Hook;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class BaseHookRenderEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class BaseHookRenderEvent extends Event
{
    /** @var  string $code the code of the hook */
    protected $code = null;

    /** @var  array $arguments an array of arguments passed to the template engine function */
    protected $arguments = array();


    public function __construct($code, array $arguments = array())
    {
        $this->code = $code;
        $this->arguments = $arguments;
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
        return array_key_exists($key, $this->arguments) ? $this->arguments[$key] : $default;
    }

    /**
     * Check if an argument exists with this key
     *
     * @param $key
     * @return bool true if it exists, else false
     */
    public function hasArgument($key)
    {
        return array_key_exists($key, $this->arguments);
    }
}
