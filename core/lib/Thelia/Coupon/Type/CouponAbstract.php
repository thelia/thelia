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

use Symfony\Component\Intl\Exception\NotImplementedException;
use Thelia\Constraint\ConstraintManager;
use Thelia\Constraint\ConditionValidator;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\AdapterInterface;
use Thelia\Coupon\ConditionCollection;
use Thelia\Coupon\RuleOrganizerInterface;
use Thelia\Exception\InvalidRuleException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Assist in writing a CouponInterface
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class CouponAbstract implements CouponInterface
{
    /** @var  AdapterInterface Provide necessary value from Thelia */
    protected $adapter = null;

    /** @var Translator Service Translator */
    protected $translator = null;

    /** @var RuleOrganizerInterface  */
    protected $organizer = null;

    /** @var ConditionCollection Array of ConditionManagerInterface */
    protected $rules = null;

    /** @var ConditionValidator Constraint validator */
    protected $constraintValidator = null;



    /** @var string Service Id  */
    protected $serviceId = null;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect)  */
    protected $amount = 0;

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

    /** @var int Max time a Coupon can be used (-1 = unlimited) */
    protected $maxUsage = -1;

    /** @var bool if Coupon is available for Products already on special offers */
    protected $isAvailableOnSpecialOffers = false;


    /**
     * Constructor
     *
     * @param AdapterInterface $adapter Service adapter
     */
    function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->translator = $adapter->getTranslator();
        $this->constraintValidator = $adapter->getConditionValidator();
    }

    /**
     * Set Rule Organizer
     *
     * @param RuleOrganizerInterface $organizer Manage Rule groups (&& and ||)
     *
     * @return $this
     */
    public function setOrganizer($organizer)
    {
        $this->organizer = $organizer;

        return $this;
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
     * A negative value
     *
     * @return float Amount removed from the Total Checkout
     */
    public function getDiscount()
    {
        return $this->amount;
    }

    /**
     * Return condition to validate the Coupon or not
     *
     * @return ConditionCollection
     */
    public function getRules()
    {
        return clone $this->rules;
    }

    /**
     * Replace the existing Rules by those given in parameter
     * If one Rule is badly implemented, no Rule will be added
     *
     * @param ConditionCollection $rules ConditionManagerInterface to add
     *
     * @return $this
     * @throws \Thelia\Exception\InvalidRuleException
     */
    public function setRules(ConditionCollection $rules)
    {
        $this->rules = $rules;

        return $this;
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
     * Check if the Coupon can be used against a
     * product already with a special offer price
     *
     * @return boolean
     */
    public function isAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }


    /**
     * Check if Coupon has been disabled by admin
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
     * Check if the Coupon is already Expired
     *
     * @return bool
     */
    public function isExpired()
    {
        $ret = true;

        $now = new \DateTime();
        if ($this->expirationDate > $now) {
            $ret = false;
        }

        return $ret;
    }

    /**
     * Get Coupon Manager service Id
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }


    /**
     * Check if the current Coupon is matching its conditions (Rules)
     * Thelia variables are given by the AdapterInterface
     *
     * @return bool
     */
    public function isMatching()
    {
        return $this->constraintValidator->isMatching($this->rules);
    }


}
