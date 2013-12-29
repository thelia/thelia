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

namespace Thelia\Core\Event\Coupon;
use Thelia\Core\Event\ActionEvent;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Occurring when a Coupon is consumed
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponConsumeEvent extends ActionEvent
{
    /** @var string Coupon code */
    protected $code = null;

    /** @var float Total discount given by this coupon */
    protected $discount = 0;

    /** @var bool If Coupon is valid or if Customer meets coupon conditions */
    protected $isValid = null;

    /**
     * Constructor
     *
     * @param string $code     Coupon code
     * @param float  $discount Total discount given by this coupon
     * @param bool   $isValid  If Coupon is valid or
     *                         if Customer meets coupon conditions
     */
    public function __construct($code, $discount = null, $isValid  = null)
    {
        $this->code = $code;
        $this->discount = $discount;
        $this->isValid = $isValid;
    }

    /**
     * Set Coupon code
     *
     * @param string $code Coupon code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get Coupon code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set total discount given by this coupon
     *
     * @param float $discount Total discount given by this coupon
     *
     * @return $this
     */
    public function setDiscount($discount)
    {
        $this->discount = $discount;

        return $this;
    }

    /**
     * Get total discount given by this coupon
     *
     * @return float
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * Set if Coupon is valid or if Customer meets coupon conditions
     *
     * @param boolean $isValid if Coupon is valid or
     *                         if Customer meets coupon conditions
     *
     * @return $this
     */
    public function setIsValid($isValid)
    {
        $this->isValid = $isValid;

        return $this;
    }

    /**
     * Get if Coupon is valid or if Customer meets coupon conditions
     *
     * @return boolean
     */
    public function getIsValid()
    {
        return $this->isValid;
    }

}
