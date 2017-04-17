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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;

class LoopResult implements \Iterator, \JsonSerializable
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
                $row->set($output[0], $row->model->{$output[1]}());
            }
        }
        if (true === $this->timestamped) {
            foreach ($this->getTimestampOutputs() as $output) {
                $row->set($output[0], $row->model->{$output[1]}());
            }
        }
        if (true === $this->countable) {
            $row->set('LOOP_COUNT', 1 + $this->getCount());
            $row->set('LOOP_TOTAL', $this->getResultDataCollectionCount());
        }

        $this->collection[] = $row;
    }
    
    /**
     * Adjust the collection once all results have been added.
     */
    public function finalizeRows()
    {
        // Fix rows LOOP_TOTAL if parseResults() did not added all resultsCollection items to the collection array
        // see https://github.com/thelia/thelia/issues/2337
        if (true === $this->countable && $this->getResultDataCollectionCount() !== $realCount = $this->getCount()) {
            foreach ($this->collection as &$item) {
                $item->set('LOOP_TOTAL', $realCount);
            }
        }
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

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        $return = [];
        foreach ($this->collection as $collection) {
            $return[] = $collection->getVarVal();
        }

        return $return;
    }
}
