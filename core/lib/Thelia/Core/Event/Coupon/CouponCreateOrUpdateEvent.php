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

use Thelia\Condition\ConditionCollection;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Coupon;
use Thelia\Model\Exception\InvalidArgumentException;

/**
 * Occurring when a Coupon is created or updated.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponCreateOrUpdateEvent extends ActionEvent
{
    /** @var ConditionCollection Array of ConditionInterface */
    protected ConditionCollection $conditions;

    /** @var float Amount that will be removed from the Checkout (Coupon Effect) */
    protected float $amount = 0;

    /** @var array Effects ready to be serialized */
    protected array $effects = [];

    /** @var Coupon Coupon model */
    protected Coupon $couponModel;

    /**
     * Constructor.
     *
     * @param string   $code                       Coupon Code
     * @param string   $serviceId                  Coupon Service id
     * @param string   $title                      Coupon title
     * @param array    $effects                    Coupon effects ready to be serialized
     *                                             'amount' key is mandatory and reflects
     *                                             the amount deduced from the cart
     * @param string   $shortDescription           Coupon short description
     * @param string   $description                Coupon description
     * @param bool     $isEnabled                  Enable/Disable
     * @param DateTime $expirationDate             Coupon expiration date
     * @param bool     $isAvailableOnSpecialOffers Is available on special offers
     * @param bool     $isCumulative               Is cumulative
     * @param bool     $isRemovingPostage          Is removing Postage
     * @param int      $maxUsage                   Coupon quantity
     * @param string   $locale                     Coupon Language code ISO (ex: fr_FR)
     * @param array    $freeShippingForCountries   ID of Countries to which shipping is free
     * @param array    $freeShippingForMethods     ID of Shipping modules for which shipping is free
     * @param bool     $perCustomerUsageCount      Usage count is per customer
     * @param DateTime $startDate                  Coupon start date
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
        protected ?DateTime $startDate = null,
    ) {
        $this->setEffects($effects);
    }

    /**
     * @param true $perCustomerUsageCount
     */
    public function setPerCustomerUsageCount(bool $perCustomerUsageCount): static
    {
        $this->perCustomerUsageCount = $perCustomerUsageCount;

        return $this;
    }

    /**
     * @return true
     */
    public function getPerCustomerUsageCount(): bool
    {
        return $this->perCustomerUsageCount;
    }

    /**
     * @return $this
     */
    public function setFreeShippingForCountries(array $freeShippingForCountries): static
    {
        $this->freeShippingForCountries = $freeShippingForCountries;

        return $this;
    }

    public function getFreeShippingForCountries(): array
    {
        return $this->freeShippingForCountries;
    }

    /**
     * @return $this
     */
    public function setFreeShippingForMethods(array $freeShippingForMethods): static
    {
        $this->freeShippingForMethods = $freeShippingForMethods;

        return $this;
    }

    public function getFreeShippingForMethods(): array
    {
        return $this->freeShippingForMethods;
    }

    /**
     * Return Coupon code (ex: XMAS).
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Return Coupon title (ex: Coupon for XMAS).
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Return Coupon short description.
     */
    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    /**
     * Return Coupon description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * If Coupon is cumulative or prevent any accumulation
     * If is cumulative you can sum Coupon effects
     * If not cancel all other Coupon and take the last given.
     */
    public function isCumulative(): bool
    {
        return $this->isCumulative;
    }

    /**
     * If Coupon is removing Checkout Postage.
     */
    public function isRemovingPostage(): bool
    {
        return $this->isRemovingPostage;
    }

    /**
     * Return effects generated by the coupon.
     *
     * @return float Amount removed from the Total Checkout
     */
    public function getAmount(): float
    {
        return $this->effects['amount'];
    }

    /**
     * Return Coupon start date.
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
     */
    public function getExpirationDate(): DateTime
    {
        return clone $this->expirationDate;
    }

    /**
     * If Coupon is available on special offers.
     */
    public function isAvailableOnSpecialOffers(): bool
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if Coupon is enabled or not.
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * Return how many time the Coupon can be used again
     * Ex : -1 unlimited.
     */
    public function getMaxUsage(): int
    {
        return $this->maxUsage;
    }

    /**
     * Get Coupon Service id (Type).
     */
    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    /**
     * Coupon Language code ISO (ex: fr_FR).
     */
    public function getLocale(): string
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
     */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /**
     * Get if the Coupon will be available on special offers or not.
     */
    public function getIsAvailableOnSpecialOffers(): bool
    {
        return $this->isAvailableOnSpecialOffers;
    }

    /**
     * Get if the Coupon effect cancel other Coupon effects.
     */
    public function getIsCumulative(): bool
    {
        return $this->isCumulative;
    }

    /**
     * Get if Coupon is enabled or not.
     */
    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getIsRemovingPostage(): bool
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
     */
    public function getCouponModel(): Coupon
    {
        return $this->couponModel;
    }

    /**
     * Get Conditions.
     *
     * @return ConditionCollection|null Array of ConditionInterface
     */
    public function getConditions(): ?ConditionCollection
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
