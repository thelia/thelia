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

namespace Thelia\Domain\Taxation\TaxEngine;

use Thelia\Model\OrderProductTax;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderProductTaxCollection implements \Iterator
{
    private ?int $position = null;
    protected $taxes = [];

    public function __construct()
    {
        foreach (\func_get_args() as $tax) {
            $this->addTax($tax);
        }
    }

    public function isEmpty(): bool
    {
        return 0 === \count($this->taxes);
    }

    public function addTax(OrderProductTax $tax): static
    {
        $this->taxes[] = $tax;

        return $this;
    }

    public function getCount(): int
    {
        return \count($this->taxes);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @return OrderProductTax
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->taxes[$this->position];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
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
        return isset($this->taxes[$this->position]);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    public function getKey($key)
    {
        return $this->taxes[$key] ?? null;
    }
}
