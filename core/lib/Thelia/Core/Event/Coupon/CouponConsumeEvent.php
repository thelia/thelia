<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Coupon;

use Thelia\Core\Event\ActionEvent;

/**
 * Occurring when a Coupon is consumed
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponConsumeEvent extends ActionEvent
{
    /** @var string Coupon code */
    protected $code;

    /** @var float Total discount given by this coupon */
    protected $discount = 0;

    /** @var bool If Coupon is valid or if Customer meets coupon conditions */
    protected $isValid;

    /** @var bool true if coupon offers free shipping  */
    protected $freeShipping = false;

    /**
     * Constructor
     *
     * @param string $code         Coupon code
     * @param float  $discount     Total discount given by this coupon
     * @param bool   $freeShipping true if coupon offers free shipping
     */
    public function __construct($code, $discount = null, $isValid = null, $freeShipping = false)
    {
        $this->code = $code;
        $this->discount = $discount;
        $this->discount = $discount;
        $this->isValid = $isValid;

        $this->freeShipping = $freeShipping;
    }

    /**
     * @param boolean $freeShipping
     */
    public function setFreeShipping($freeShipping)
    {
        $this->freeShipping = $freeShipping;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getFreeShipping()
    {
        return $this->freeShipping;
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
