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

namespace Thelia\Coupon\Validator;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represent a Price
 * Positive value with currency
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class PriceParam extends RuleParameterAbstract
{
    /** @var float Positive Float to compare with */
    protected $price = null;

    /** @var string Currency Code ISO 4217 EUR|USD|GBP */
    protected $currency = null;

    /**
     * Constructor
     *
     * @param float  $price    Positive float
     * @param string $currency Currency Code ISO 4217 EUR|USD|GBP
     */
    public function __construct($price, $currency)
    {
        $this->price = $price;
        $this->currency = $currency;
    }

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Get price
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Compare the current object to the passed $other.
     *
     * Returns 0 if they are semantically equal, 1 if the other object
     * is less than the current one, or -1 if its more than the current one.
     *
     * This method should not check for identity using ===, only for semantically equality for example
     * when two different DateTime instances point to the exact same Date + TZ.
     *
     * @param mixed $other Object
     *
     * @throws \InvalidArgumentException
     * @return int
     */
    public function compareTo($other)
    {
        if (!is_float($other)) {
            throw new \InvalidArgumentException(
                'PriceParam can compare only positive float'
            );
        }

        $epsilon = 0.00001;

        $ret = -1;
        if (abs($this->price - $other) < $epsilon) {
            $ret = 0;
        } elseif ($this->price > $other) {
            $ret = 1;
        } else {
            $ret = -1;
        }

        return $ret;
    }

    /**
     * Get Parameter value to test against
     *
     * @return float
     */
    public function getValue()
    {
        return $this->price;
    }
}