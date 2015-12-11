<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Coupon;

use Thelia\Core\Event\ActionEvent;
use Thelia\Condition\ConditionCollection;
use Thelia\Model\Coupon;

/**
 * Occurring when a Coupon is created or updated
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponCreateOrUpdateEvent extends ActionEvent
{
    /** @var ConditionCollection Array of ConditionInterface */
    protected $conditions = null;

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

    /** @var \DateTime Coupon start date */
    protected $startDate = null;

    /** @var \DateTime Coupon expiration date */
    protected $expirationDate = null;

    /** @var bool if Coupon is cumulative */
    protected $isCumulative = false;

    /** @var bool if Coupon is removing postage */
    protected $isRemovingPostage = false;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect)  */
    protected $amount = 0;

    /** @var array Effects ready to be serialized */
    protected $effects = array();

    /** @var int Max time a Coupon can be used (-1 = unlimited) */
    protected $maxUsage = -1;

    /** @var bool if Coupon is available for Products already on special offers */
    protected $isAvailableOnSpecialOffers = false;

    /** @var Coupon Coupon model */
    protected $couponModel = null;

    /** @var string Coupon Service id */
    protected $serviceId;

    /** @var string Language code ISO (ex: fr_FR) */
    protected $locale = null;

    /** @var array ID of Countries to which shipping is free */
    protected $freeShippingForCountries;

    /** @var  array ID of Shipping modules for which shipping is free */
    protected $freeShippingForMethods;

    /** @var true if usage count is per customer only */
    protected $perCustomerUsageCount;

    /**
     * Constructor
     *
     * @param string    $code                       Coupon Code
     * @param string    $serviceId                  Coupon Service id
     * @param string    $title                      Coupon title
     * @param array     $effects                    Coupon effects ready to be serialized
     *                                              'amount' key is mandatory and reflects
     *                                              the amount deduced from the cart
     * @param string    $shortDescription           Coupon short description
     * @param string    $description                Coupon description
     * @param bool      $isEnabled                  Enable/Disable
     * @param \DateTime $expirationDate             Coupon expiration date
     * @param boolean   $isAvailableOnSpecialOffers Is available on special offers
     * @param boolean   $isCumulative               Is cumulative
     * @param boolean   $isRemovingPostage          Is removing Postage
     * @param int       $maxUsage                   Coupon quantity
     * @param string    $locale                     Coupon Language code ISO (ex: fr_FR)
     * @param array     $freeShippingForCountries   ID of Countries to which shipping is free
     * @param array     $freeShippingForMethods     ID of Shipping modules for which shipping is free
     * @param boolean   $perCustomerUsageCount      Usage count is per customer
     * @param \DateTime $startDate                  Coupon start date
     */
    public function __construct(
        $code,
        $serviceId,
        $title,
        array $effects,
        $shortDescription,
        $description,
        $isEnabled,
        \DateTime $expirationDate,
        $isAvailableOnSpecialOffers,
        $isCumulative,
        $isRemovingPostage,
        $maxUsage,
        $locale,
        $freeShippingForCountries,
        $freeShippingForMethods,
        $perCustomerUsageCount,
        \DateTime $startDate = null
    ) {
        $this->code = $code;
        $this->description = $description;
        $this->expirationDate = $expirationDate;
        $this->isAvailableOnSpecialOffers = $isAvailableOnSpecialOffers;
        $this->isCumulative = $isCumulative;
        $this->isEnabled = $isEnabled;
        $this->isRemovingPostage = $isRemovingPostage;
        $this->maxUsage = $maxUsage;
        $this->shortDescription = $shortDescription;
        $this->title = $title;
        $this->serviceId = $serviceId;
        $this->locale = $locale;
        $this->setEffects($effects);
        $this->freeShippingForCountries = $freeShippingForCountries;
        $this->freeShippingForMethods = $freeShippingForMethods;
        $this->perCustomerUsageCount = $perCustomerUsageCount;
        $this->startDate = $startDate;
    }

    /**
     * @param true $perCustomerUsageCount
     */
    public function setPerCustomerUsageCount($perCustomerUsageCount)
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    /**
     * @return true
     */
    public function getPerCustomerUsageCount()
    {
        return $this->perCustomerUsageCount;
    }

    /**
     * @param  array $freeShippingForCountries
     * @return $this
     */
    public function setFreeShippingForCountries($freeShippingForCountries)
    {
        $this->freeShippingForCountries = $freeShippingForCountries;

        return $this;
    }

    /**
     * @return array
     */
    public function getFreeShippingForCountries()
    {
        return $this->freeShippingForCountries;
    }

    /**
     * @param  array $freeShippingForMethods
     * @return $this
     */
    public function setFreeShippingForMethods($freeShippingForMethods)
    {
        $this->freeShippingForMethods = $freeShippingForMethods;

        return $this;
    }

    /**
     * @return array
     */
    public function getFreeShippingForMethods()
    {
        return $this->freeShippingForMethods;
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
        return $this->effects['amount'];
    }

    /**
     * Return Coupon start date
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        if ($this->startDate === null) {
            return null;
        }
        return clone $this->startDate;
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
     * Get Coupon Service id (Type)
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Coupon Language code ISO (ex: fr_FR)
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set effects ready to be serialized
     *
     * @param  array                                            $effects Effect ready to be serialized
     *                                                                   Needs at least the key 'amount'
     *                                                                   with the amount removed from the cart
     * @throws \Thelia\Model\Exception\InvalidArgumentException
     */
    public function setEffects(array $effects)
    {
        // Amount is now optionnal.
        $this->amount = isset($effects['amount']) ? $effects['amount'] : 0;

        $this->effects = $effects;
    }

    /**
     * Get effects ready to be serialized
     *
     * @return array
     */
    public function getEffects()
    {
        return $this->effects;
    }

    /**
     * Get if the Coupon will be available on special offers or not
     *
     * @return boolean
     */
    public function getIsAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if the Coupon effect cancel other Coupon effects
     *
     * @return boolean
     */
    public function getIsCumulative()
    {
        return $this->isCumulative;
    }

    /**
     * Get if Coupon is enabled or not
     *
     * @return boolean
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * @return boolean
     */
    public function getIsRemovingPostage()
    {
        return $this->isRemovingPostage;
    }

    /**
     * Set Coupon Model
     *
     * @param Coupon $couponModel Coupon Model
     *
     * @return $this
     */
    public function setCouponModel(Coupon $couponModel)
    {
        $this->couponModel = $couponModel;

        return $this;
    }

    /**
     * Return Coupon Model
     *
     * @return \Thelia\Model\Coupon
     */
    public function getCouponModel()
    {
        return $this->couponModel;
    }

    /**
     * Get Conditions
     *
     * @return null|ConditionCollection Array of ConditionInterface
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Set Conditions
     *
     * @param ConditionCollection $conditions Array of ConditionInterface
     *
     * @return $this
     */
    public function setConditions(ConditionCollection $conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }
}
