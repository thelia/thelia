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

namespace Thelia\Constraint;

use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Coupon\CouponRuleCollection;


/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage how Constraint could interact
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConstraintManager
{
    /** @var  CouponAdapterInterface Provide necessary value from Thelia*/
    protected $adapter;

    /** @var array CouponRuleCollection to process*/
    protected $rules = null;

    /**
     * Constructor
     *
     * @param CouponAdapterInterface $adapter Provide necessary value from Thelia
     * @param CouponRuleCollection   $rules   Rules associated with the Constraint
     */
    function __construct(CouponAdapterInterface $adapter, CouponRuleCollection $rules)
    {
        $this->adapter = $adapter;
        $this->rule = $rules;
    }

    /**
     * Check if the current Coupon is matching its conditions (Rules)
     * Thelia variables are given by the CouponAdapterInterface
     *
     * @return bool
     */
    public function isMatching()
    {
        $isMatching = true;

        /** @var CouponRuleInterface $rule */
        foreach ($this->rules->getRules() as $rule) {
            if (!$rule->isMatching($this->adapter)) {
                $isMatching = false;
            }
        }

        return $isMatching;
    }


}