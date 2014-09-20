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

namespace Thelia\Core\Hook;

use Iterator;

/**
 * Class FragmentBag
 * @package Thelia\Core\Hook
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FragmentBag implements Iterator
{
    private $position;

    /** @var array $fragments */
    protected $fragments;

    public function __construct()
    {
        $this->position  = 0;
        $this->fragments = array();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->fragments[$this->position];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     *                 Returns true on success or false on failure.
     */
    public function valid()
    {
        return isset($this->fragments[$this->position]);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Clears all parameters.
     *
     * @api
     */
    public function clear()
    {
        $this->position  = 0;
        $this->fragments = array();
    }

    public function isEmpty()
    {
        return count($this->fragments) == 0;
    }

    public function getCount()
    {
        return count($this->fragments);
    }

    public function add($data)
    {
        $fragment = new Fragment($data);
        $this->addFragment($fragment);
    }

    public function addFragment(Fragment $fragment)
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
