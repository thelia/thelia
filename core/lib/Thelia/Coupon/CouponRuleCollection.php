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

namespace Thelia\Coupon;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Thelia\Coupon\Rule\CouponRuleInterface;
use Thelia\Exception\InvalidRuleException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage a set of v
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponRuleCollection
{
    /** @var array Array of CouponRuleInterface */
    protected $rules = array();

    /**
     * Constructor
     *
     * @param array $rules Array of CouponRuleInterface
     *
     * @throws \Thelia\Exception\InvalidRuleException
     */
    function __construct(array $rules)
    {
        foreach ($rules as $rule) {
            if (!$rule instanceof CouponRuleInterface) {
                throw new InvalidRuleException(get_class());
            }
        }
        $this->rules = $rules;
    }

    /**
     * Get Rules
     *
     * @return array Array of CouponRuleInterface
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Add a CouponRuleInterface to the Collection
     *
     * @param CouponRuleInterface $rule Rule
     *
     * @return $this
     */
    public function add(CouponRuleInterface $rule)
    {
        $this->rules[] = $rule;

        return $this;
    }


}