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

namespace Thelia\Core\Hook;

use Iterator;

/**
 * Class FragmentBag.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FragmentBag implements Iterator
{
    private $position;

    /** @var array */
    protected $fragments;

    public function __construct()
    {
        $this->position = 0;
        $this->fragments = [];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @return mixed can return any type
     */
    public function current()
    {
        return $this->fragments[$this->position];
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
        ++$this->position;
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
        return $this->position;
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
        return isset($this->fragments[$this->position]);
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
        $this->position = 0;
    }

    /**
     * Clears all parameters.
     *
     * @api
     */
    public function clear(): void
    {
        $this->position = 0;
        $this->fragments = [];
    }

    public function isEmpty()
    {
        return \count($this->fragments) == 0;
    }

    public function getCount()
    {
        return \count($this->fragments);
    }

    public function add($data): void
    {
        $fragment = new Fragment($data);
        $this->addFragment($fragment);
    }

    public function addFragment(Fragment $fragment): void
    {
        $this->fragments[] = $fragment;
    }

    /**
     * Gets the all keys fragment.
     *
     * @return array An array of parameters
     *
     * @api
     */
    public function keys()
    {
        return array_keys($this->fragments);
    }
}
