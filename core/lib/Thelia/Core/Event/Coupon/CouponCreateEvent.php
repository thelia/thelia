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
use Thelia\Model\Coupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Occurring when a Coupon is created
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponCreateEvent extends ActionEvent
{
    /**
     * @var Coupon Coupon being created
     */
    protected $createdCoupon;

    /**
     * Constructor
     *
     * @param Coupon $coupon Coupon being created
     */
    public function __construct(Coupon $coupon)
    {
        $this->createdCoupon = $coupon;
    }

    /**
     * Modify Coupon being created
     *
     * @param Coupon $createdCoupon Coupon being created
     *
     * @return $this
     */
    public function setCreatedCoupon(Coupon $createdCoupon)
    {
        $this->createdCoupon = $createdCoupon;

        return $this;
    }

    /**
     * Get Coupon being created
     *
     * @return Coupon
     */
    public function getCreatedCoupon()
    {
        return clone $this->createdCoupon;
    }


}
