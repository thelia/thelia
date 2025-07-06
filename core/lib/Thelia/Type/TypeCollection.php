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

namespace Thelia\Type;

use Iterator;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TypeCollection implements \Iterator
{
    private ?int $position = null;

    protected $types = [];

    public function __construct()
    {
        foreach (\func_get_args() as $type) {
            $this->addType($type);
        }
    }

    public function isEmpty(): bool
    {
        return \count($this->types) == 0;
    }

    public function addType(TypeInterface $type): static
    {
        $this->types[] = $type;

        return $this;
    }

    public function getCount(): int
    {
        return \count($this->types);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @return TypeInterface
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->types[$this->position];
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
    #[\ReturnTypeWillChange]
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
        return isset($this->types[$this->position]);
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

    public function isValid($value): bool
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
        return $this->types[$key] ?? null;
    }
}
