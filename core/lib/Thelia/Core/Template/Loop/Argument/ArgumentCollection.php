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

namespace Thelia\Core\Template\Loop\Argument;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ArgumentCollection implements \Iterator
{
    private $arguments = [];

    public function __construct()
    {
        $this->addArguments(\func_get_args(), true);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasKey($key)
    {
        return isset($this->arguments[$key]);
    }

    /**
     * @param $key
     *
     * @return Argument|null
     */
    public function get($key)
    {
        return $this->hasKey($key) ? $this->arguments[$key] : null;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return \count($this->arguments) == 0;
    }

    /**
     * @param $force
     *
     * @return ArgumentCollection
     */
    public function addArguments(array $argumentList, $force = true)
    {
        foreach ($argumentList as $argument) {
            $this->addArgument($argument, $force);
        }

        return $this;
    }

    /**
     * @param $force
     *
     * @return ArgumentCollection
     */
    public function addArgument(Argument $argument, $force = true)
    {
        if (isset($this->arguments[$argument->name]) && !$force) {
            return $this;
        }

        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    /**
     * @param array $argumentNames array with names of arguments to remove
     *
     * @return ArgumentCollection
     *
     * @since 2.2.0-beta1
     */
    public function removeArguments(array $argumentNames)
    {
        foreach ($argumentNames as $argumentName) {
            $this->removeArgument($argumentName);
        }

        return $this;
    }

    /**
     * @param string $argumentName name of the argument to remove
     *
     * @return ArgumentCollection
     *
     * @since 2.2.0-beta1
     */
    public function removeArgument(string $argumentName)
    {
        if (isset($this->arguments[$argumentName])) {
            unset($this->arguments[$argumentName]);
        }

        return $this;
    }

    public function getCount()
    {
        return \count($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @return Argument
     */
    public function current()
    {
        return current($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
     *
     * @return void any returned value is ignored
     */
    public function next(): void
    {
        next($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element.
     *
     * @see http://php.net/manual/en/iterator.key.php
     *
     * @return mixed scalar on success, or null on failure
     */
    public function key()
    {
        return key($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid.
     *
     * @see http://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return $this->key() !== null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     *
     * @return void any returned value is ignored
     */
    public function rewind(): void
    {
        reset($this->arguments);
    }

    public function getHash()
    {
        $arguments = $this->arguments;

        if (\array_key_exists('name', $arguments)) {
            unset($arguments['name']);
        }

        $string = '';
        foreach ($arguments as $key => $argument) {
            $string .= $key.'='.$argument->getRawValue();
        }

        return md5($string);
    }
}
