<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;

class LoopResult implements \Iterator
{
    private $position;
    protected $collection = array();

    public $resultsCollection = null;

    protected $versioned = false;
    protected $timestamped = false;
    protected $countable = false;

    public function __construct($resultsCollection)
    {
        $this->position = 0;
        $this->resultsCollection = $resultsCollection;
    }

    /**
     * @param boolean $countable
     */
    public function setCountable($countable = true)
    {
        $this->countable = true === $countable;
    }

    /**
     * @param boolean $timestamped
     */
    public function setTimestamped($timestamped = true)
    {
        $this->timestamped = true === $timestamped;
    }

    /**
     * @param boolean $versioned
     */
    public function setVersioned($versioned = true)
    {
        $this->versioned = true === $versioned;
    }

    public function isEmpty()
    {
        return count($this->collection) == 0;
    }

    public function addRow(LoopResultRow $row)
    {
        if (true === $this->versioned) {
            foreach ($this->getVersionOutputs() as $output) {
                $row->set($output[0], $row->model->$output[1]());
            }
        }
        if (true === $this->timestamped) {
            foreach ($this->getTimestampOutputs() as $output) {
                $row->set($output[0], $row->model->$output[1]());
            }
        }
        if (true === $this->countable) {
            $row->set('LOOP_COUNT', 1 + $this->getCount());
            $row->set('LOOP_TOTAL', $this->getResultDataCollectionCount());
        }

        $this->collection[] = $row;
    }

    public function getCount()
    {
        return count($this->collection);
    }

    public function getResultDataCollectionCount()
    {
        if ($this->resultsCollection instanceof ObjectCollection || $this->resultsCollection instanceof PropelModelPager) {
            return $this->resultsCollection->count();
        } elseif (is_array($this->resultsCollection)) {
            return count($this->resultsCollection);
        } else {
            return 0;
        }
    }

    public function getResultDataCollection()
    {
        return $this->resultsCollection;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return \Thelia\Core\Template\Element\LoopResultRow
     */
    public function current()
    {
        return $this->collection[$this->position];
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
        return isset($this->collection[$this->position]);
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

    protected function getTimestampOutputs()
    {
        return array(
            array('CREATE_DATE', 'getCreatedAt'),
            array('UPDATE_DATE', 'getUpdatedAt'),
        );
    }

    protected function getVersionOutputs()
    {
        return array(
            array('VERSION', 'getVersion'),
            array('VERSION_DATE', 'getVersionCreatedAt'),
            array('VERSION_AUTHOR', 'getVersionCreatedBy'),
        );
    }
}
