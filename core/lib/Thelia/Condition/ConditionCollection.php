<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Condition;

use ArrayAccess;
use Countable;
use Iterator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\Implementation\ConditionInterface;

/**
 * Manage a set of ConditionInterface
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionCollection implements Iterator, Countable, ArrayAccess
{
    /** @var array Array of ConditionInterface */
    protected $conditions = array();

    /**
     * (PHP 5 &gt;= 5.0.0)
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     *
     * @return mixed Can return any type.
     */
    public function current()
    {
        $var = current($this->conditions);

        return $var;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     *
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        next($this->conditions);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     *
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        $var = key($this->conditions);

        return $var;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     *
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        $key = key($this->conditions);
        $var = ($key !== null && $key !== false);

        return $var;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     *
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        reset($this->conditions);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->conditions);
    }

    /**
     * (PHP 5 >= 5.0.0)
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset
     * An offset to check for.
     *
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return isset($this->conditions[$offset]);
    }

    /**
     * (PHP 5 >= 5.0.0)
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset
     * The offset to retrieve.
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return isset($this->conditions[$offset]) ? $this->conditions[$offset] : null;
    }

    /**
     * (PHP 5 >= 5.0.0)
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset
     * The offset to assign the value to.
     * @param mixed $value
     * The value to set.
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->conditions[] = $value;
        } else {
            $this->conditions[$offset] = $value;
        }
    }

    /**
     * (PHP 5 >= 5.0.0)
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset
     * The offset to unset.
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->conditions[$offset]);
    }

    /**
     * Allow to compare 2 set of conditions
     *
     * @return string Jsoned data
     */
    public function __toString()
    {
        $arrayToSerialize = array();
        /** @var ConditionInterface $condition */
        foreach ($this as $condition) {
            $arrayToSerialize[] = $condition->getSerializableCondition();
        }

        return json_encode($arrayToSerialize);
    }

}