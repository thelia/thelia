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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Constraint\Rule\CouponRuleInterface;
use Thelia\Constraint\Rule\SerializableRule;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Manage a set of CouponRuleInterface
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
     */
    function __construct()
    {

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

    /**
     * Check if there is at least one rule in the collection
     *
     * @return bool
     */
    public function isEmpty()
    {
        return isEmpty($this->rules);
    }


}