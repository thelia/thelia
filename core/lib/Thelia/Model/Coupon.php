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

use Propel\Runtime\Propel;
use Thelia\Coupon\CouponRuleCollection;
use Thelia\Model\Base\Coupon as BaseCoupon;
use Thelia\Model\Map\CouponTableMap;

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
     * Constructor
     *
     * @param string               $code                       Coupon Code
     * @param string               $title                      Coupon title
     * @param float                $amount                     Amount removed from the Total Checkout
     * @param string               $effect                     Coupon effect
     * @param string               $shortDescription           Coupon short description
     * @param string               $description                Coupon description
     * @param boolean              $isEnabled                  Enable/Disable
     * @param \DateTime            $expirationDate             Coupon expiration date
     * @param boolean              $isAvailableOnSpecialOffers Is available on special offers
     * @param boolean              $isCumulative               Is cumulative
     * @param boolean              $isRemovingPostage          Is removing Postage
     * @param int                  $maxUsage                   Coupon quantity
     * @param CouponRuleCollection $rules                      CouponRuleInterface to add
     * @param string               $lang                       Coupon Language code ISO (ex: fr_FR)
     */
    function createOrUpdate($code, $title, $amount, $effect, $shortDescription, $description, $isEnabled, $expirationDate, $isAvailableOnSpecialOffers, $isCumulative, $maxUsage, $rules, $lang = null)
    {
        $this->setCode($code)
            ->setTitle($title)
            ->setShortDescription($shortDescription)
            ->setDescription($description)
            ->setType($effect)
            ->setAmount($amount)
            ->setType($amount)
            ->setIsEnabled($isEnabled)
            ->setExpirationDate($expirationDate)
            ->setIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers)
            ->setIsCumulative($isCumulative)
            ->setMaxUsage($maxUsage)
            ->setRules($rules);

        // Set object language (i18n)
        if (!is_null($lang)) {
            $this->setLang($lang);
        }

        $con = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $this->save($con);
            $con->commit();

        } catch(\Exception $e) {
            $con->rollback();
            throw $e;
        }
    }

    /**
     * Set the value of [serialized_rules] column.
     *
     * @param CouponRuleCollection $rules A set of Rules
     *
     * @return   \Thelia\Model\Coupon The current object (for fluent API support)
     */
    public function setRules(CouponRuleCollection $rules)
    {
        $serializedRules = null;
        if ($rules !== null) {

            $serializedRules = (string) base64_encode(serialize($rules));
        }

        if ($this->serialized_rules !== $serializedRules) {
            $this->serialized_rules = $serializedRules;
            $this->modifiedColumns[] = CouponTableMap::SERIALIZED_RULES;
        }

        return $this;
    }


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
