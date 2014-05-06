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

namespace Thelia\TaxEngine;

use Thelia\Model\OrderProductTax;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class OrderProductTaxCollection implements \Iterator
{
    private $position;
    protected $taxes = array();

    public function __construct()
    {
        foreach (func_get_args() as $tax) {
            $this->addTax($tax);
        }
    }

    public function isEmpty()
    {
        return count($this->taxes) == 0;
    }

    /**
     * @param OrderProductTax $tax
     *
     * @return OrderProductTaxCollection
     */
    public function addTax(OrderProductTax $tax)
    {
        $this->taxes[] = $tax;

        return $this;
    }

    public function getCount()
    {
        return count($this->taxes);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return OrderProductTax
     */
    public function current()
    {
        return $this->taxes[$this->position];
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
        return isset($this->taxes[$this->position]);
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

    public function getKey($key)
    {
        return isset($this->taxes[$key]) ? $this->taxes[$key] : null;
    }
}
