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

namespace Thelia\Core\Event\Condition;

use Thelia\Core\Event\ActionEvent;
use Thelia\Coupon\ConditionCollection;
use Thelia\Coupon\Type\CouponInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/29/13
 * Time: 3:45 PM
 *
 * Occurring when a Condition is created or updated
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionCreateOrUpdateEvent extends ActionEvent
{
    /** @var ConditionCollection Array of ConditionManagerInterface */
    protected $conditions = null;

    /** @var CouponInterface Coupon model associated with this conditions */
    protected $couponModel = null;

    /**
     * Constructor
     *
     * @param ConditionCollection $conditions Array of ConditionManagerInterface
     */
    public function __construct(ConditionCollection $conditions)
    {
        $this->conditions = $conditions;
    }

    /**
     * Get Conditions
     *
     * @return null|ConditionCollection Array of ConditionManagerInterface
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Set Conditions
     *
     * @param ConditionCollection $conditions Array of ConditionManagerInterface
     *
     * @return $this
     */
    public function setConditions(ConditionCollection $conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Set Coupon Model associated to this condition
     *
     * @param CouponInterface $couponModel Coupon Model
     *
     * @return $this
     */
    public function setCouponModel($couponModel)
    {
        $this->couponModel = $couponModel;

        return $this;
    }

    /**
     * Get Coupon Model associated to this condition
     *
     * @return null|CouponInterface
     */
    public function getCouponModel()
    {
        return $this->couponModel;
    }
}
