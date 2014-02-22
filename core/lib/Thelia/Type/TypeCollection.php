<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class TypeCollection implements \Iterator
{
    private $position;
    protected $types = array();

    public function __construct()
    {
        foreach (func_get_args() as $type) {
            $this->addType($type);
        }
    }

    public function isEmpty()
    {
        return count($this->types) == 0;
    }

    /**
     * @param TypeInterface $type
     *
     * @return TypeCollection
     */
    public function addType(TypeInterface $type)
    {
        $this->types[] = $type;

        return $this;
    }

    public function getCount()
    {
        return count($this->types);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return \Thelia\Type\TypeInterface
     */
    public function current()
    {
        return $this->types[$this->position];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->position++;
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
        return isset($this->types[$this->position]);
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
     * @param $value
     *
     * @return bool
     */
    public function isValid($value)
    {
        foreach ($this as $type) {
            if ($type->isValid($value)) {
                return true;
            }
        }

        return false;
    }

    public function getFormattedValue($value)
    {
        foreach ($this as $type) {
            if ($type->isValid($value)) {
                return $type->getFormattedValue($value);
            }
        }

        return null;
    }

    public function getKey($key)
    {
        return isset($this->types[$key]) ? $this->types[$key] : null;
    }
}
