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

namespace Thelia\Core\Template\Loop\Argument;

use Iterator;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ArgumentCollection implements \Iterator
{
    private array $arguments = [];

    public function __construct()
    {
        $this->addArguments(\func_get_args(), true);
    }

    public function hasKey($key): bool
    {
        return isset($this->arguments[$key]);
    }

    public function get($key): ?Argument
    {
        return $this->hasKey($key) ? $this->arguments[$key] : null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->arguments;
    }

    public function addArguments(array $argumentList, $force = true): static
    {
        foreach ($argumentList as $argument) {
            $this->addArgument($argument, $force);
        }

        return $this;
    }

    public function addArgument(Argument $argument, $force = true): static
    {
        if (isset($this->arguments[$argument->name]) && !$force) {
            return $this;
        }

        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    /**
     * @param array $argumentNames array with names of arguments to remove
     */
    public function removeArguments(array $argumentNames): static
    {
        foreach ($argumentNames as $argumentName) {
            $this->removeArgument($argumentName);
        }

        return $this;
    }

    /**
     * @param string $argumentName name of the argument to remove
     */
    public function removeArgument(string $argumentName): static
    {
        if (isset($this->arguments[$argumentName])) {
            unset($this->arguments[$argumentName]);
        }

        return $this;
    }

    public function getCount(): int
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
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
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
    #[\ReturnTypeWillChange]
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
        return null !== $this->key();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     */
    public function rewind(): void
    {
        reset($this->arguments);
    }

    public function getHash(): string
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
