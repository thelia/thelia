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
namespace Thelia\Core\Template\Loop\Argument;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class ArgumentCollection implements \Iterator
{
    private $arguments = array();

    public function __construct()
    {
        $this->addArguments(func_get_args(), true);
    }

    public function hasKey($key)
    {
        return isset($this->arguments[$key]);
    }

    public function get($key)
    {
        return $this->hasKey($key) ? $this->arguments[$key] : null;
    }

    public function isEmpty()
    {
        return count($this->arguments) == 0;
    }

    /**
     * @param array $argumentList
     * @param       $force
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
     * @param Argument $argument
     * @param          $force
     *
     * @return ArgumentCollection
     */
    public function addArgument(Argument $argument, $force = true)
    {
        if (isset($this->arguments[$argument->name]) && ! $force) {
            return $this;
        }

        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    public function getCount()
    {
        return count($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return Argument
     */
    public function current()
    {
        return current($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        next($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return key($this->arguments);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->key() !== null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        reset($this->arguments);
    }

    public function getHash()
    {
        $arguments = $this->arguments;

        if (array_key_exists('name', $arguments)) {
            unset($arguments['name']);
        }

        $string = '';
        foreach ($arguments as $key => $argument) {
            $string .= $key.'='.$argument->getRawValue();
        }

        return md5($string);
    }
}
