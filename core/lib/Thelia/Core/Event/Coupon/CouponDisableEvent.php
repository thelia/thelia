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
use Thelia\Model\Coupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Occurring when a Coupon is disabled
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponDisableEvent extends ActionEvent
{
    /** @var int Coupon id  */
    protected $couponId;

    /** @var Coupon Coupon being disabled */
    protected $disabledCoupon;

    /**
     * Constructor
     *
     * @param int $id Coupon Id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Get Coupon id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set Coupon id
     *
     * @param int $id Coupon id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get Coupon being disabled
     *
     * @return Coupon
     */
    public function getDisabledCoupon()
    {
        return $this->disabledCoupon;
    }

    /**
     * Set Coupon to be disabled
     *
     * @param Coupon $disabledCoupon Coupon to disable
     *
     * @return $this
     */
    public function setDisabledCoupon(Coupon $disabledCoupon)
    {
        $this->disabledCoupon = $disabledCoupon;

        return $this;
    }
}
