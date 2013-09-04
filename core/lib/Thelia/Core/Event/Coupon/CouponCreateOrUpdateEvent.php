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
use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Event\ActionEvent;
use Thelia\Coupon\CouponRuleCollection;
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
class CouponCreateOrUpdateEvent extends ActionEvent
{
    /** @var CouponRuleCollection Array of CouponRuleInterface */
    protected $rules = null;

    /** @var string Coupon code (ex: XMAS) */
    protected $code = null;

    /** @var string Coupon title (ex: Coupon for XMAS) */
    protected $title = null;

    /** @var string Coupon short description */
    protected $shortDescription = null;

    /** @var string Coupon description */
    protected $description = null;

    /** @var bool if Coupon is enabled */
    protected $isEnabled = false;

    /** @var \DateTime Coupon expiration date */
    protected $expirationDate = null;

    /** @var bool if Coupon is cumulative */
    protected $isCumulative = false;

    /** @var bool if Coupon is removing postage */
    protected $isRemovingPostage = false;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect)  */
    protected $amount = 0;

    /** @var int Max time a Coupon can be used (-1 = unlimited) */
    protected $maxUsage = -1;

    /** @var bool if Coupon is available for Products already on special offers */
    protected $isAvailableOnSpecialOffers = false;

    /** @var Coupon Coupon model */
    protected $coupon = null;

    /** @var string Coupon effect */
    protected $effect;

    /** @var string Language code ISO (ex: fr_FR) */
    protected $lang = null;

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
    function __construct(
        $code,
        $title,
        $amount,
        $effect,
        $shortDescription,
        $description,
        $isEnabled,
        \DateTime $expirationDate,
        $isAvailableOnSpecialOffers,
        $isCumulative,
        $isRemovingPostage,
        $maxUsage,
        $rules,
        $lang
    ) {
        $this->amount = $amount;
        $this->code = $code;
        $this->description = $description;
        $this->expirationDate = $expirationDate;
        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
        $this->isCumulative = $isCumulative;
        $this->isEnabled = $isEnabled;
        $this->isRemovingPostage = $isRemovingPostage;
        $this->maxUsage = $maxUsage;
        $this->rules = $rules;
        $this->shortDescription = $shortDescription;
        $this->title = $title;
        $this->effect = $effect;
        $this->lang = $lang;
    }

    /**
     * Return Coupon code (ex: XMAS)
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Return Coupon title (ex: Coupon for XMAS)
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return Coupon short description
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * Return Coupon description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given
     *
     * @return bool
     */
    public function isCumulative()
    {
        return $this->isCumulative;
    }

    /**
     * If Coupon is removing Checkout Postage
     *
     * @return bool
     */
    public function isRemovingPostage()
    {
        return $this->isRemovingPostage;
    }

    /**
     * Return effects generated by the coupon
     *
     * @return float Amount removed from the Total Checkout
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Return condition to validate the Coupon or not
     *
     * @return CouponRuleCollection
     */
    public function getRules()
    {
        return clone $this->rules;
    }

    /**
     * Return Coupon expiration date
     *
     * @return \DateTime
     */
    public function getExpirationDate()
    {
        return clone $this->expirationDate;
    }

    /**
     * If Coupon is available on special offers
     *
     * @return boolean
     */
    public function isAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if Coupon is enabled or not
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited
     *
     * @return int
     */
    public function getMaxUsage()
    {
        return $this->maxUsage;
    }

    /**
     * Get Coupon effect
     *
     * @return string
     */
    public function getEffect()
    {
        return $this->effect;
    }

    /**
     * Coupon Language code ISO (ex: fr_FR)
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param \Thelia\Model\Coupon $coupon
     */
    public function setCoupon($coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * @return \Thelia\Model\Coupon
     */
    public function getCoupon()
    {
        return $this->coupon;
    }



}
