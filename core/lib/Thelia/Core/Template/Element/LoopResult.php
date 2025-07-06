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
namespace Thelia\Core\Template\Element;

use Iterator;
use JsonSerializable;
use ReturnTypeWillChange;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;

class LoopResult implements Iterator, JsonSerializable
{
    private int $position = 0;

    protected $collection = [];

    protected $versioned = false;

    protected $timestamped = false;

    protected $countable = false;

    public function __construct(public $resultsCollection)
    {
    }

    /**
     * @param bool $countable
     */
    public function setCountable($countable = true): void
    {
        $this->countable = true === $countable;
    }

    /**
     * @param bool $timestamped
     */
    public function setTimestamped($timestamped = true): void
    {
        $this->timestamped = true === $timestamped;
    }

    /**
     * @param bool $versioned
     */
    public function setVersioned($versioned = true): void
    {
        $this->versioned = true === $versioned;
    }

    public function isEmpty(): bool
    {
        return \count($this->collection) == 0;
    }

    public function addRow(LoopResultRow $row, $key = null): void
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

        if (null !== $key) {
            $this->collection[$key] = $row;
        } else {
            $this->collection[] = $row;
        }
    }

    /**
     * @param int $key
     */
    public function remove($key): void
    {
        if (isset($this->collection[$key])) {
            unset($this->collection[$key]);
        }
    }

    /**
     * Adjust the collection once all results have been added.
     */
    public function finalizeRows(): void
    {
        // Fix rows LOOP_TOTAL if parseResults() did not added all resultsCollection items to the collection array
        // see https://github.com/thelia/thelia/issues/2337
        if (true === $this->countable && $this->getResultDataCollectionCount() !== $realCount = $this->getCount()) {
            foreach ($this->collection as &$item) {
                $item->set('LOOP_TOTAL', $realCount);
            }
        }
    }

    public function getCount(): int
    {
        return \count($this->collection);
    }

    public function getResultDataCollectionCount(): int
    {
        if ($this->resultsCollection instanceof ObjectCollection || $this->resultsCollection instanceof PropelModelPager) {
            return $this->resultsCollection->count();
        }

        if (\is_array($this->resultsCollection)) {
            return \count($this->resultsCollection);
        }

        return 0;
    }

    public function getResultDataCollection()
    {
        return $this->resultsCollection;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @return LoopResultRow
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->collection[$this->position];
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
    #[ReturnTypeWillChange]
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
        return isset($this->collection[$this->position]);
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

    protected function getTimestampOutputs(): array
    {
        return [
            ['CREATE_DATE', 'getCreatedAt'],
            ['UPDATE_DATE', 'getUpdatedAt'],
        ];
    }

    protected function getVersionOutputs(): array
    {
        return [
            ['VERSION', 'getVersion'],
            ['VERSION_DATE', 'getVersionCreatedAt'],
            ['VERSION_AUTHOR', 'getVersionCreatedBy'],
        ];
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $return = [];
        foreach ($this->collection as $collection) {
            $return[] = $collection->getVarVal();
        }

        return $return;
    }
}
