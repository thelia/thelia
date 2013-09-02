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

namespace Thelia\Model;

use Thelia\Coupon\CouponRuleCollection;
use Thelia\Model\Base\Coupon as BaseCoupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Used to provide an effect (mostly a discount)
 * at the end of the Customer checkout tunnel
 * It will be usable for a Customer only if it matches the Coupon criteria (Rules)
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseCoupon
{
    /**
     * Set the value of [serialized_rules] column.
     *
     * @param CouponRuleCollection $rules A set of Rules
     *
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setRules(CouponRuleCollection $rules)
    {
        if ($rules !== null) {

            $v = (string) base64_encode(serialize($rules));
        }

        if ($this->serialized_rules !== $v) {
            $this->serialized_rules = $v;
            $this->modifiedColumns[] = CouponTableMap::SERIALIZED_RULES;
        }


        return $this;
    } // setSerializedRules()


    /**
     * Get the [serialized_rules] column value.
     *
     * @return CouponRuleCollection Rules ready to be processed
     */
    public function getRules()
    {
        return unserialize(base64_decode($this->serialized_rules));
    }
}
