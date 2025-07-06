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
namespace Thelia\Core\Event\Coupon;

use Thelia\Core\Event\ActionEvent;

/**
 * Occurring when a Coupon is consumed.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponConsumeEvent extends ActionEvent
{
    /**
     * Constructor.
     *
     * @param string $code         Coupon code
     * @param float  $discount     Total discount given by this coupon
     * @param bool   $freeShipping true if coupon offers free shipping
     * @param bool $isValid
     */
    public function __construct(protected $code, protected $discount = null, /** @var bool If Coupon is valid or if Customer meets coupon conditions */
    protected $isValid = null, protected $freeShipping = false)
    {
        $this->discount = $this->discount;
    }

    /**
     * @param bool $freeShipping
     */
    public function setFreeShipping($freeShipping): static
    {
        $this->freeShipping = $freeShipping;

        return $this;
    }

    /**
     * @return bool
     */
    public function getFreeShipping()
    {
        return $this->freeShipping;
    }

    /**
     * Set Coupon code.
     *
     * @param string $code Coupon code
     *
     * @return $this
     */
    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get Coupon code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set total discount given by this coupon.
     *
     * @param float $discount Total discount given by this coupon
     *
     * @return $this
     */
    public function setDiscount($discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    /**
     * Get total discount given by this coupon.
     *
     * @return float
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * Set if Coupon is valid or if Customer meets coupon conditions.
     *
     * @param bool $isValid if Coupon is valid or
     *                      if Customer meets coupon conditions
     *
     * @return $this
     */
    public function setIsValid($isValid): static
    {
        $this->isValid = $isValid;

        return $this;
    }

    /**
     * Get if Coupon is valid or if Customer meets coupon conditions.
     *
     * @return bool
     */
    public function getIsValid()
    {
        return $this->isValid;
    }
}
