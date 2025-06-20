<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Core\Event\Coupon;


use Thelia\Model\Exception\InvalidArgumentException;
use Thelia\Condition\ConditionCollection;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Coupon;

/**
 * Occurring when a Coupon is created or updated.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCreateOrUpdateEvent extends ActionEvent
{
    /** @var ConditionCollection Array of ConditionInterface */
    protected $conditions;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect) */
    protected $amount = 0;

    /** @var array Effects ready to be serialized */
    protected $effects = [];

    /** @var Coupon Coupon model */
    protected $couponModel;

    /**
     * Constructor.
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
     * @param DateTime $expirationDate Coupon expiration date
     * @param bool      $isAvailableOnSpecialOffers Is available on special offers
     * @param bool      $isCumulative               Is cumulative
     * @param bool      $isRemovingPostage          Is removing Postage
     * @param int       $maxUsage                   Coupon quantity
     * @param string    $locale                     Coupon Language code ISO (ex: fr_FR)
     * @param array     $freeShippingForCountries   ID of Countries to which shipping is free
     * @param array     $freeShippingForMethods     ID of Shipping modules for which shipping is free
     * @param bool      $perCustomerUsageCount      Usage count is per customer
     * @param DateTime $startDate Coupon start date
     */
    public function __construct(
        protected $code,
        protected $serviceId,
        protected $title,
        array $effects,
        protected $shortDescription,
        protected $description,
        protected $isEnabled,
        protected DateTime $expirationDate,
        protected $isAvailableOnSpecialOffers,
        protected $isCumulative,
        protected $isRemovingPostage,
        protected $maxUsage,
        protected $locale,
        protected $freeShippingForCountries,
        protected $freeShippingForMethods,
        protected $perCustomerUsageCount,
        protected ?DateTime $startDate = null
    ) {
        $this->setEffects($effects);
    }

    /**
     * @param true $perCustomerUsageCount
     */
    public function setPerCustomerUsageCount($perCustomerUsageCount): static
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
     * @param array $freeShippingForCountries
     *
     * @return $this
     */
    public function setFreeShippingForCountries($freeShippingForCountries): static
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
     * @param array $freeShippingForMethods
     *
     * @return $this
     */
    public function setFreeShippingForMethods($freeShippingForMethods): static
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
     * Return Coupon code (ex: XMAS).
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Return Coupon title (ex: Coupon for XMAS).
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return Coupon short description.
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * Return Coupon description.
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
     * If not cancel all other Coupon and take the last given.
     *
     * @return bool
     */
    public function isCumulative()
    {
        return $this->isCumulative;
    }

    /**
     * If Coupon is removing Checkout Postage.
     *
     * @return bool
     */
    public function isRemovingPostage()
    {
        return $this->isRemovingPostage;
    }

    /**
     * Return effects generated by the coupon.
     *
     * @return float Amount removed from the Total Checkout
     */
    public function getAmount()
    {
        return $this->effects['amount'];
    }

    /**
     * Return Coupon start date.
     *
     * @return DateTime
     */
    public function getStartDate(): ?DateTime
    {
        if (!$this->startDate instanceof DateTime) {
            return null;
        }

        return clone $this->startDate;
    }

    /**
     * Return Coupon expiration date.
     *
     * @return DateTime
     */
    public function getExpirationDate()
    {
        return clone $this->expirationDate;
    }

    /**
     * If Coupon is available on special offers.
     *
     * @return bool
     */
    public function isAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if Coupon is enabled or not.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited.
     *
     * @return int
     */
    public function getMaxUsage()
    {
        return $this->maxUsage;
    }

    /**
     * Get Coupon Service id (Type).
     *
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Coupon Language code ISO (ex: fr_FR).
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set effects ready to be serialized.
     *
     * @param array $effects Effect ready to be serialized
     *                       Needs at least the key 'amount'
     *                       with the amount removed from the cart
     *
     * @throws InvalidArgumentException
     */
    public function setEffects(array $effects): void
    {
        // Amount is now optionnal.
        $this->amount = $effects['amount'] ?? 0;

        $this->effects = $effects;
    }

    /**
     * Get effects ready to be serialized.
     *
     * @return array
     */
    public function getEffects()
    {
        return $this->effects;
    }

    /**
     * Get if the Coupon will be available on special offers or not.
     *
     * @return bool
     */
    public function getIsAvailableOnSpecialOffers()
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if the Coupon effect cancel other Coupon effects.
     *
     * @return bool
     */
    public function getIsCumulative()
    {
        return $this->isCumulative;
    }

    /**
     * Get if Coupon is enabled or not.
     *
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * @return bool
     */
    public function getIsRemovingPostage()
    {
        return $this->isRemovingPostage;
    }

    /**
     * Set Coupon Model.
     *
     * @param Coupon $couponModel Coupon Model
     *
     * @return $this
     */
    public function setCouponModel(Coupon $couponModel): static
    {
        $this->couponModel = $couponModel;

        return $this;
    }

    /**
     * Return Coupon Model.
     *
     * @return Coupon
     */
    public function getCouponModel()
    {
        return $this->couponModel;
    }

    /**
     * Get Conditions.
     *
     * @return ConditionCollection|null Array of ConditionInterface
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Set Conditions.
     *
     * @param ConditionCollection $conditions Array of ConditionInterface
     *
     * @return $this
     */
    public function setConditions(ConditionCollection $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }
}
