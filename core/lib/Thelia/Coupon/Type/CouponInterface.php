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

namespace Thelia\Coupon\Type;

use Thelia\Coupon\ConditionCollection;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Represents a Coupon ready to be processed in a Checkout process
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
interface CouponInterface
{
    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName();

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip();

    /**
     * Get Coupon Manager service Id
     *
     * @return string
     */
    public function getServiceId();

    /**
     * Set Coupon
     *
     * @param CouponInterface $adapter                    Provides necessary value from Thelia
     * @param string          $code                       Coupon code (ex: XMAS)
     * @param string          $title                      Coupon title (ex: Coupon for XMAS)
     * @param string          $shortDescription           Coupon short description
     * @param string          $description                Coupon description
     * @param float           $effect                     Coupon amount/percentage to deduce
     * @param bool            $isCumulative               If Coupon is cumulative
     * @param bool            $isRemovingPostage          If Coupon is removing postage
     * @param bool            $isAvailableOnSpecialOffers If available on Product already
     *                                                    on special offer price
     * @param bool            $isEnabled                  False if Coupon is disabled by admin
     * @param int             $maxUsage                   How many usage left
     * @param \Datetime       $expirationDate             When the Code is expiring
     */
    public function set(
        $adapter,
        $code,
        $title,
        $shortDescription,
        $description,
        $effect,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate);

    /**
     * Return Coupon code (ex: XMAS)
     *
     * @return string
     */
    public function getCode();

    /**
     * Return Coupon title (ex: Coupon for XMAS)
     *
     * @return string
     */
    public function getTitle();

    /**
     * Return Coupon short description
     *
     * @return string
     */
    public function getShortDescription();

    /**
     * Return Coupon description
     *
     * @return string
     */
    public function getDescription();

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given
     *
     * @return bool
     */
    public function isCumulative();

    /**
     * If Coupon is removing Checkout Postage
     *
     * @return bool
     */
    public function isRemovingPostage();



    /**
     * Return condition to validate the Coupon or not
     *
     * @return ConditionCollection A set of ConditionManagerInterface
     */
    public function getConditions();

    /**
     * Replace the existing Rules by those given in parameter
     * If one Rule is badly implemented, no Rule will be added
     *
     * @param ConditionCollection $rules ConditionManagerInterface to add
     *
     * @return $this
     * @throws \Thelia\Exception\InvalidConditionException
     */
    public function setConditions(ConditionCollection $rules);

    /**
     * Return Coupon expiration date
     *
     * @return \DateTime
     */
    public function getExpirationDate();

    /**
     * Check if the Coupon can be used against a
     * product already with a special offer price
     *
     * @return boolean
     */
    public function isAvailableOnSpecialOffers();


    /**
     * Check if Coupon has been disabled by admin
     *
     * @return boolean
     */
    public function isEnabled();

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited
     *
     * @return int
     */
    public function getMaxUsage();

    /**
     * Check if the Coupon is already Expired
     *
     * @return bool
     */
    public function isExpired();


    /**
     * Return effects generated by the coupon
     * A positive value
     *
     * Effects could also affect something else than the final Checkout price
     * CouponAdapter $adapter could be use to directly pass a Session value
     * some would wish to modify
     * Hence affecting a wide variety of Thelia elements
     *
     * @return float Amount removed from the Total Checkout
     */
    public function exec();

    /**
     * Check if the current Coupon is matching its conditions (Rules)
     * Thelia variables are given by the FacadeInterface
     *
     * @return bool
     */
    public function isMatching();

}
