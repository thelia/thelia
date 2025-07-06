<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\Propel;
use Thelia\Model\Base\Coupon as BaseCoupon;
use Thelia\Model\Map\CouponTableMap;

/**
 * Used to provide an effect (mostly a discount)
 * at the end of the Customer checkout tunnel
 * It will be usable for a Customer only if it matches the Coupon criteria (Rules).
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class Coupon extends BaseCoupon
{
    // Define the value of an unlimited coupon usage.
    public const UNLIMITED_COUPON_USE = -1;

    /**
     * Create or Update this Coupon.
     *
     * @param string    $code                       Coupon Code
     * @param string    $title                      Coupon title
     * @param array     $effects                    Ready to be serialized in JSON effect params
     * @param string    $type                       Coupon type
     * @param bool      $isRemovingPostage          Is removing Postage
     * @param string    $shortDescription           Coupon short description
     * @param string    $description                Coupon description
     * @param bool      $isEnabled                  Enable/Disable
     * @param \DateTime $expirationDate             Coupon expiration date
     * @param bool      $isAvailableOnSpecialOffers Is available on special offers
     * @param bool      $isCumulative               Is cumulative
     * @param int       $maxUsage                   Coupon quantity
     * @param string    $defaultSerializedRule      Serialized default rule added if none found
     * @param string    $locale                     Coupon Language code ISO (ex: fr_FR)
     * @param array     $freeShippingForCountries   ID of Countries to which shipping is free
     * @param array     $freeShippingForMethods     ID of Shipping modules for which shipping is free
     * @param bool      $perCustomerUsageCount      True if usage coiunt is per customer
     *
     * @throws \Exception
     */
    public function createOrUpdate(
        $code,
        $title,
        array $effects,
        $type,
        $isRemovingPostage,
        $shortDescription,
        $description,
        $isEnabled,
        $expirationDate,
        $isAvailableOnSpecialOffers,
        $isCumulative,
        $maxUsage,
        $defaultSerializedRule,
        $locale,
        $freeShippingForCountries,
        $freeShippingForMethods,
        $perCustomerUsageCount,
        $startDate = null
    ): void {
        $con = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            $this
                ->setCode($code)
                ->setType($type)
                ->setEffects($effects)
                ->setIsRemovingPostage($isRemovingPostage)
                ->setIsEnabled($isEnabled)
                ->setStartDate($startDate)
                ->setExpirationDate($expirationDate)
                ->setIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers)
                ->setIsCumulative($isCumulative)
                ->setMaxUsage($maxUsage)
                ->setPerCustomerUsageCount($perCustomerUsageCount)
                ->setLocale($locale)
                ->setTitle($title)
                ->setShortDescription($shortDescription)
                ->setDescription($description)
            ;

            // If no rule given, set default rule
            if (null === $this->getSerializedConditions()) {
                $this->setSerializedConditions($defaultSerializedRule);
            }

            $this->save();

            // Update countries and modules relation for free shipping
            CouponCountryQuery::create()->filterByCouponId($this->id)->delete();
            CouponModuleQuery::create()->filterByCouponId($this->id)->delete();

            foreach ($freeShippingForCountries as $countryId) {
                if ($countryId <= 0) {
                    continue;
                }

                $couponCountry = new CouponCountry();

                $couponCountry
                    ->setCouponId($this->getId())
                    ->setCountryId($countryId)
                    ->save();
            }

            foreach ($freeShippingForMethods as $moduleId) {
                if ($moduleId <= 0) {
                    continue;
                }

                $couponModule = new CouponModule();

                $couponModule
                    ->setCouponId($this->getId())
                    ->setModuleId($moduleId)
                    ->save()
                ;
            }

            $con->commit();
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Create or Update this coupon condition.
     *
     * @param string $serializableConditions Serialized conditions ready to be saved
     * @param string $locale                 Coupon Language code ISO (ex: fr_FR)
     *
     * @throws \Exception
     */
    public function createOrUpdateConditions($serializableConditions, $locale): void
    {
        $this->setSerializedConditions($serializableConditions);

        // Set object language (i18n)
        if (null !== $locale) {
            $this->setLocale($locale);
        }

        $this->save();
    }

    /**
     * Set Coupon amount.
     *
     * @param float $amount Amount deduced from the Cart
     *
     * @return $this
     */
    public function setAmount($amount)
    {
        $effects = $this->unserializeEffects($this->getSerializedEffects());
        $effects['amount'] = (float) $amount;
        $this->setEffects($effects);

        return $this;
    }

    /**
     * Get the amount removed from the coupon to the cart.
     *
     * @return float
     */
    public function getAmount()
    {
        // Amount is now optional
        $amount = $this->getEffects()['amount'] ?? 0;

        return (float) $amount;
    }

    /**
     * Get the Coupon effects.
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return array
     */
    public function getEffects()
    {
        $effects = $this->unserializeEffects($this->getSerializedEffects());

        return $effects;
    }

    /**
     * Get the Coupon effects.
     *
     * @param array $effects Effect ready to be serialized
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return $this
     */
    public function setEffects(array $effects)
    {
        $this->setSerializedEffects($this->serializeEffects($effects));

        return $this;
    }

    /**
     * Return unserialized effects.
     *
     * @param string $serializedEffects Serialized effect string to unserialize
     *
     * @return array
     */
    public function unserializeEffects($serializedEffects)
    {
        $effects = json_decode($serializedEffects, true);

        return $effects;
    }

    /**
     * Return serialized effects.
     *
     * @param array $unserializedEffects Unserialized array string to serialize
     *
     * @return string
     */
    public function serializeEffects(array $unserializedEffects)
    {
        $effects = json_encode($unserializedEffects);

        return $effects;
    }

    /**
     * Return the countries for which free shipping is valid.
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getFreeShippingForCountries()
    {
        return CouponCountryQuery::create()->filterByCouponId($this->getId())->find();
    }

    /**
     * Return the modules for which free shipping is valid.
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getFreeShippingForModules()
    {
        return CouponModuleQuery::create()->filterByCouponId($this->getId())->find();
    }

    public function isUsageUnlimited()
    {
        return $this->getMaxUsage() == self::UNLIMITED_COUPON_USE;
    }

    /**
     * Get coupon usage left, either overall, or per customer.
     *
     * @param int|null $customerId the ID of the ordering customer
     *
     * @return int the usage left
     */
    public function getUsagesLeft($customerId = null)
    {
        $usageLeft = $this->getMaxUsage();

        if ($this->getPerCustomerUsageCount()) {
            // Get usage left for current customer. If the record is not found,
            // it means that the customer has not yes used this coupon.
            if (null !== $couponCustomerCount = CouponCustomerCountQuery::create()
                    ->filterByCouponId($this->getId())
                    ->filterByCustomerId($customerId)
                    ->findOne()) {
                // The coupon has already been used -> remove this customer's usage count
                $usageLeft -= $couponCustomerCount->getCount();
            }
        }

        return $usageLeft;
    }
}
